<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\ExamScratchPhoto;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $examPapers = ExamPaper::with('creator')
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'exam_papers' => $examPapers,
        ]);
    }

    public function start(Request $request, ExamPaper $examPaper)
    {
        // 已有进行中或草稿异常挂起中的记录：直接恢复，不新建
        $existingRecord = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [
                ExamRecord::STATUS_IN_PROGRESS,
                ExamRecord::STATUS_SCRATCH_PENDING,
            ])
            ->latest('id')
            ->first();

        if ($existingRecord) {
            return response()->json([
                'message' => $existingRecord->status === ExamRecord::STATUS_SCRATCH_PENDING
                    ? '您有一场考试的草稿异常待处理，请补拍照片或等待教师审批'
                    : '您已经开始这场考试',
                'exam_record' => $this->formatRecord($existingRecord, $examPaper),
            ]);
        }

        $record = ExamRecord::create([
            'user_id' => $request->user()->id,
            'exam_paper_id' => $examPaper->id,
            'start_time' => now(),
            'status' => 'in_progress',
            'scratch_exception_status' => ExamRecord::EXCEPTION_NONE,
        ]);

        return response()->json([
            'message' => '考试开始',
            'exam_record' => $this->formatRecord($record, $examPaper),
            'exam_paper' => $this->formatPaper($examPaper),
            'questions' => $examPaper->questions()->get()->map(fn ($q) => $this->formatQuestion($q)),
        ]);
    }

    public function getQuestions(Request $request, ExamPaper $examPaper)
    {
        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [
                ExamRecord::STATUS_IN_PROGRESS,
                ExamRecord::STATUS_SCRATCH_PENDING,
            ])
            ->latest('id')
            ->firstOrFail();

        $questions = $examPaper->questions()->get();

        return response()->json([
            'exam_record' => $this->formatRecord($record, $examPaper),
            'exam_paper' => $this->formatPaper($examPaper),
            'questions' => $questions->map(fn ($q) => $this->formatQuestion($q)),
        ]);
    }

    /**
     * 上传草稿纸照片（开考前空白页 / 交卷前最终页）
     */
    public function uploadScratchPhoto(Request $request, ExamPaper $examPaper)
    {
        if (!$examPaper->allow_scratch_paper) {
            return response()->json([
                'message' => '本场考试不允许使用纸质草稿纸',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'phase' => 'required|in:before_start,before_submit',
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'taken_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->firstOrFail();

        if (!in_array($record->status, [
            ExamRecord::STATUS_IN_PROGRESS,
            ExamRecord::STATUS_SCRATCH_PENDING,
        ], true)) {
            return response()->json(['message' => '考试已结束，不能再上传草稿照片'], 422);
        }

        // 已挂起的记录，仅在教师驳回（补拍）或尚未有审批结果时允许上传
        if ($record->status === ExamRecord::STATUS_SCRATCH_PENDING
            && !in_array($record->scratch_exception_status, [
                ExamRecord::EXCEPTION_NONE,
                ExamRecord::EXCEPTION_REJECTED,
                ExamRecord::EXCEPTION_PENDING,
            ], true)) {
            return response()->json(['message' => '当前异常处理状态下不能补拍照片'], 422);
        }

        // 同一阶段只保留最新照片（补拍即重拍，旧照片仍保留在存储中但断开关联更稳妥：直接覆盖最新记录）
        $existing = ExamScratchPhoto::where('exam_record_id', $record->id)
            ->where('phase', $request->phase)
            ->latest('id')
            ->first();

        $path = $request->file('photo')->store(
            'scratch-photos/' . $record->id,
            'local'
        );

        if (!$path) {
            return response()->json(['message' => '照片保存失败，请重试'], 500);
        }

        if ($existing) {
            $oldPath = $existing->photo_path;
            $existing->update([
                'photo_path' => $path,
                'taken_at' => $request->taken_at ? Carbon::parse($request->taken_at) : now(),
                'server_time' => now(),
            ]);
            if ($oldPath !== $path) {
                Storage::disk('local')->delete($oldPath);
            }
            $photo = $existing->fresh();
        } else {
            $photo = ExamScratchPhoto::create([
                'exam_record_id' => $record->id,
                'user_id' => $request->user()->id,
                'phase' => $request->phase,
                'photo_path' => $path,
                'taken_at' => $request->taken_at ? Carbon::parse($request->taken_at) : now(),
                'server_time' => now(),
            ]);
        }

        return response()->json([
            'message' => $request->phase === ExamScratchPhoto::PHASE_BEFORE_START
                ? '空白草稿纸照片已上传'
                : '最终页草稿纸照片已上传',
            'photo' => [
                'id' => $photo->id,
                'phase' => $photo->phase,
                'taken_at' => $photo->taken_at,
                'server_time' => $photo->server_time,
                'url' => url("/api/exams/scratch-photos/{$photo->id}"),
                'data_uri' => $this->photoDataUri($photo),
            ],
            'scratch_status' => $this->scratchStatus($record),
        ], 201);
    }

    /**
     * 学生查看本场考试草稿照片完成情况
     */
    public function scratchStatusForStudent(Request $request, ExamPaper $examPaper)
    {
        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [
                ExamRecord::STATUS_IN_PROGRESS,
                ExamRecord::STATUS_SCRATCH_PENDING,
            ])
            ->firstOrFail();

        return response()->json([
            'allow_scratch_paper' => (bool) $examPaper->allow_scratch_paper,
            'scratch_status' => $this->scratchStatus($record),
            'record_status' => $record->status,
            'scratch_exception_status' => $record->scratch_exception_status,
            'scratch_exception_reason' => $record->scratch_exception_reason,
            'scratch_exception_remark' => $record->scratch_exception_remark,
        ]);
    }

    /**
     * 读取草稿照片（鉴权后输出，避免照片公开访问）
     */
    public function viewScratchPhoto(Request $request, ExamScratchPhoto $scratchPhoto)
    {
        $user = $request->user();
        $record = $scratchPhoto->examRecord;

        $canView = $record->user_id === $user->id
            || in_array($user->role, ['admin', 'teacher']);

        if (!$canView) {
            return response()->json(['message' => '无权查看该照片'], 403);
        }

        if (!Storage::disk('local')->exists($scratchPhoto->photo_path)) {
            return response()->json(['message' => '照片文件不存在'], 404);
        }

        return response()->file(Storage::disk('local')->path($scratchPhoto->photo_path));
    }

    public function submit(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
            'answers.*.answered_at' => 'nullable|date',
            'hand_to_teacher' => 'nullable|boolean',
            'exception_reason' => 'required_if:hand_to_teacher,true|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $scratch = $this->scratchStatus($record);

        // 需要草稿纸且照片不全
        if ($examPaper->allow_scratch_paper && !$scratch['complete']) {
            // 选择交老师处理：记录挂起，等待教师审批，不计分
            if ($request->boolean('hand_to_teacher')) {
                $record->update([
                    'status' => ExamRecord::STATUS_SCRATCH_PENDING,
                    'end_time' => now(),
                    'scratch_exception_status' => ExamRecord::EXCEPTION_PENDING,
                    'scratch_exception_reason' => $request->exception_reason,
                ]);

                return response()->json([
                    'message' => '已提交给监考老师处理，请等待审批结果',
                    'error_code' => 'SCRATCH_PENDING_TEACHER',
                    'record_status' => $record->status,
                    'missing_phases' => $scratch['missing_phases'],
                ], 202);
            }

            // 默认拦截：不允许交卷
            return response()->json([
                'message' => '草稿纸照片未拍齐，不能交卷。请先补拍缺失照片，或选择"交给老师处理"。',
                'error_code' => 'SCRATCH_PHOTO_MISSING',
                'missing_phases' => $scratch['missing_phases'],
                'missing_labels' => $scratch['missing_labels'],
                'scratch_status' => $scratch,
            ], 422);
        }

        return $this->gradeAndFinish($record, $examPaper, $request->answers);
    }

    public function myRecords(Request $request)
    {
        $records = ExamRecord::with(['examPaper', 'scratchPhotos'])
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'records' => $records,
        ]);
    }

    public function showRecord(Request $request, ExamRecord $record)
    {
        $user = $request->user();

        if ($record->user_id !== $user->id && !in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $record->load([
            'examPaper.questions',
            'answers.question',
            'scratchPhotos',
            'exceptionHandler',
        ]);

        return response()->json([
            'record' => $record,
        ]);
    }

    /**
     * 学生在异常被驳回后，补拍草稿照片并重新交卷
     */
    public function resubmitAfterException(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
            'answers.*.answered_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', ExamRecord::STATUS_SCRATCH_PENDING)
            ->where('scratch_exception_status', ExamRecord::EXCEPTION_REJECTED)
            ->firstOrFail();

        $scratch = $this->scratchStatus($record);

        if (!$scratch['complete']) {
            return response()->json([
                'message' => '草稿纸照片仍未拍齐，请先补拍后再交卷。',
                'error_code' => 'SCRATCH_PHOTO_MISSING',
                'missing_phases' => $scratch['missing_phases'],
                'missing_labels' => $scratch['missing_labels'],
                'scratch_status' => $scratch,
            ], 422);
        }

        return $this->gradeAndFinish($record, $examPaper, $request->answers);
    }

    /**
     * 监考回放：草稿照片时间线 + 答题时间点
     */
    public function proctorReview(Request $request, ExamRecord $record)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权查看监考回放'], 403);
        }

        $record->load([
            'user:id,username,real_name,email',
            'examPaper:id,title,allow_scratch_paper,total_time',
            'answers.question:id,title,type',
            'scratchPhotos',
            'exceptionHandler:id,username,real_name',
        ]);

        // 合并照片与答题事件，按时间排序，形成监考时间线
        $timeline = collect();

        foreach ($record->scratchPhotos as $photo) {
            $timeline->push([
                'type' => 'scratch_photo',
                'phase' => $photo->phase,
                'phase_label' => ExamScratchPhoto::PHASES[$photo->phase] ?? $photo->phase,
                'time' => $photo->taken_at,
                'server_time' => $photo->server_time,
                'photo_id' => $photo->id,
                'photo_url' => url("/api/exams/scratch-photos/{$photo->id}"),
                'photo_data_uri' => $this->photoDataUri($photo),
            ]);
        }

        foreach ($record->answers as $answer) {
            $timeline->push([
                'type' => 'answer',
                'time' => $answer->answered_at ?: $answer->created_at,
                'question_id' => $answer->question_id,
                'question_title' => $answer->question->title ?? null,
                'answer_excerpt' => mb_substr((string) $answer->answer, 0, 60),
                'score' => $answer->score,
                'is_correct' => $answer->is_correct,
            ]);
        }

        return response()->json([
            'record' => [
                'id' => $record->id,
                'status' => $record->status,
                'status_label' => ExamRecord::STATUSES[$record->status] ?? $record->status,
                'score' => $record->score,
                'start_time' => $record->start_time,
                'end_time' => $record->end_time,
                'student' => $record->user,
                'exam_paper' => $record->examPaper,
                'scratch_exception_status' => $record->scratch_exception_status,
                'scratch_exception_status_label' =>
                    ExamRecord::EXCEPTION_STATUSES[$record->scratch_exception_status] ?? $record->scratch_exception_status,
                'scratch_exception_reason' => $record->scratch_exception_reason,
                'scratch_exception_remark' => $record->scratch_exception_remark,
                'scratch_exception_handled_at' => $record->scratch_exception_handled_at,
                'exception_handler' => $record->exceptionHandler,
            ],
            'scratch_photos' => $record->scratchPhotos->map(fn ($p) => [
                'id' => $p->id,
                'phase' => $p->phase,
                'phase_label' => ExamScratchPhoto::PHASES[$p->phase] ?? $p->phase,
                'taken_at' => $p->taken_at,
                'server_time' => $p->server_time,
                'url' => url("/api/exams/scratch-photos/{$p->id}"),
                'data_uri' => $this->photoDataUri($p),
            ]),
            'answers' => $record->answers->map(fn ($a) => [
                'question_id' => $a->question_id,
                'question_title' => $a->question->title ?? null,
                'answer' => $a->answer,
                'answered_at' => $a->answered_at ?: $a->created_at,
                'is_correct' => $a->is_correct,
                'score' => $a->score,
            ]),
            'timeline' => $timeline
                ->filter(fn ($e) => !empty($e['time']))
                ->sortBy([
                    fn ($a, $b) => strcmp((string) $a['time'], (string) $b['time']),
                ])
                ->values(),
        ]);
    }

    /**
     * 教师：草稿异常处理列表
     */
    public function exceptionList(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $query = ExamRecord::with(['user:id,username,real_name,email', 'examPaper:id,title,created_by'])
            ->where('scratch_exception_status', ExamRecord::EXCEPTION_PENDING)
            ->orderByDesc('id');

        // 非管理员教师只看自己创建的试卷
        if ($user->role === 'teacher') {
            $query->whereHas('examPaper', fn ($q) => $q->where('created_by', $user->id));
        }

        return response()->json([
            'records' => $query->paginate($request->input('per_page', 15)),
        ]);
    }

    /**
     * 教师：处理草稿异常（approve=放行并计分 / reject=驳回，学生补拍后再交）
     */
    public function handleException(Request $request, ExamRecord $record)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权处理'], 403);
        }

        if ($user->role === 'teacher' && $record->examPaper->created_by !== $user->id) {
            return response()->json(['message' => '只能处理自己试卷的异常'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'remark' => 'nullable|string|max:500',
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required_with:answers|exists:questions,id',
            'answers.*.answer' => 'required_with:answers|string',
            'answers.*.answered_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($record->scratch_exception_status !== ExamRecord::EXCEPTION_PENDING) {
            return response()->json(['message' => '该异常已处理，请勿重复操作'], 422);
        }

        if ($request->action === 'reject') {
            $record->update([
                'scratch_exception_status' => ExamRecord::EXCEPTION_REJECTED,
                'scratch_exception_handled_by' => $user->id,
                'scratch_exception_handled_at' => now(),
                'scratch_exception_remark' => $request->remark,
            ]);

            return response()->json([
                'message' => '已驳回，学生需补拍草稿照片后重新交卷',
                'record_status' => $record->status,
            ]);
        }

        // 放行：无答案数据时仅标记（学生可在前端再次提交），这里支持教师直接用已暂存答案计分
        $record->update([
            'scratch_exception_status' => ExamRecord::EXCEPTION_APPROVED,
            'scratch_exception_handled_by' => $user->id,
            'scratch_exception_handled_at' => now(),
            'scratch_exception_remark' => $request->remark,
        ]);

        if ($request->has('answers')) {
            return $this->gradeAndFinish(
                $record,
                $record->examPaper,
                $request->answers,
                ExamRecord::EXCEPTION_APPROVED
            );
        }

        return response()->json([
            'message' => '已放行，学生可直接交卷',
            'record_status' => $record->status,
        ]);
    }

    /**
     * 教师放行后，学生直接交卷计分
     */
    public function submitAfterApproval(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
            'answers.*.answered_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', ExamRecord::STATUS_SCRATCH_PENDING)
            ->where('scratch_exception_status', ExamRecord::EXCEPTION_APPROVED)
            ->firstOrFail();

        return $this->gradeAndFinish($record, $examPaper, $request->answers, ExamRecord::EXCEPTION_APPROVED);
    }

    protected function checkAnswer(Question $question, string $userAnswer): bool
    {
        $correctAnswer = $question->answer;

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            case 'multiple_choice':
                $userAnswers = explode(',', strtoupper(trim($userAnswer)));
                $correctAnswers = explode(',', strtoupper(trim($correctAnswer)));
                sort($userAnswers);
                sort($correctAnswers);
                return $userAnswers === $correctAnswers;
            case 'fill_blank':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            default:
                return false;
        }
    }

    /**
     * 计分并结束考试（统一收口）
     */
    protected function gradeAndFinish(
        ExamRecord $record,
        ExamPaper $examPaper,
        array $answers,
        string $exceptionStatus = ExamRecord::EXCEPTION_NONE
    ) {
        return DB::transaction(function () use ($record, $examPaper, $answers, $exceptionStatus) {
            $totalScore = 0;
            $examPaper->loadMissing('questions');
            $questionMap = $examPaper->questions->keyBy('id');

            foreach ($answers as $answerData) {
                $question = $questionMap->get($answerData['question_id']);
                if (!$question) {
                    continue;
                }

                $isCorrect = $this->checkAnswer($question, $answerData['answer']);
                $score = $isCorrect ? $question->pivot->score : 0;

                ExamRecordAnswer::create([
                    'exam_record_id' => $record->id,
                    'question_id' => $answerData['question_id'],
                    'answer' => $answerData['answer'],
                    'answered_at' => !empty($answerData['answered_at'])
                        ? Carbon::parse($answerData['answered_at'])
                        : now(),
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);

                $totalScore += $score;
            }

            $record->update([
                'end_time' => now(),
                'score' => $totalScore,
                'status' => ExamRecord::STATUS_GRADED,
                'scratch_exception_status' => $exceptionStatus,
            ]);

            return response()->json([
                'message' => '提交成功',
                'score' => $totalScore,
                'exam_record' => $record->load('answers'),
            ]);
        });
    }

    /**
     * 草稿照片完成情况
     */
    protected function scratchStatus(ExamRecord $record): array
    {
        $photos = ExamScratchPhoto::where('exam_record_id', $record->id)->get();
        $phases = $photos->pluck('phase')->unique()->values()->all();

        $required = [
            ExamScratchPhoto::PHASE_BEFORE_START,
            ExamScratchPhoto::PHASE_BEFORE_SUBMIT,
        ];

        $missing = array_values(array_diff($required, $phases));
        $missingLabels = array_map(
            fn ($p) => ExamScratchPhoto::PHASES[$p] ?? $p,
            $missing
        );

        return [
            'required' => $required,
            'uploaded_phases' => array_values($phases),
            'missing_phases' => $missing,
            'missing_labels' => $missingLabels,
            'complete' => empty($missing),
            'photos' => $photos->map(fn ($p) => [
                'id' => $p->id,
                'phase' => $p->phase,
                'phase_label' => ExamScratchPhoto::PHASES[$p->phase] ?? $p->phase,
                'taken_at' => $p->taken_at,
                'server_time' => $p->server_time,
                'url' => url("/api/exams/scratch-photos/{$p->id}"),
                'data_uri' => $this->photoDataUri($p),
            ])->values(),
        ];
    }

    /**
     * 把草稿照片转成内联 data URI，便于 <img> 在 Bearer 鉴权下直接显示
     */
    protected function photoDataUri(ExamScratchPhoto $photo): ?string
    {
        if (!Storage::disk('local')->exists($photo->photo_path)) {
            return null;
        }

        try {
            $binary = Storage::disk('local')->get($photo->photo_path);
            $mime = Storage::disk('local')->mimeType($photo->photo_path) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function formatPaper(ExamPaper $paper): array
    {
        return [
            'id' => $paper->id,
            'title' => $paper->title,
            'total_time' => $paper->total_time,
            'total_score' => $paper->total_score,
            'allow_scratch_paper' => (bool) $paper->allow_scratch_paper,
        ];
    }

    protected function formatQuestion($q): array
    {
        return [
            'id' => $q->id,
            'type' => $q->type,
            'title' => $q->title,
            'options' => $q->options,
            'score' => $q->pivot->score,
        ];
    }

    protected function formatRecord(ExamRecord $record, ExamPaper $paper): array
    {
        $data = $record->toArray();
        $data['allow_scratch_paper'] = (bool) $paper->allow_scratch_paper;
        if ($paper->allow_scratch_paper) {
            $data['scratch_status'] = $this->scratchStatus($record);
        }
        return $data;
    }
}
