<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\Question;
use App\Support\ScratchPaperGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    protected ScratchPaperGuard $scratchGuard;

    public function __construct(ScratchPaperGuard $scratchGuard)
    {
        $this->scratchGuard = $scratchGuard;
    }
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
        $existingRecord = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingRecord) {
            $existingState = $this->scratchGuard->state($existingRecord, $examPaper);

            return response()->json([
                'message' => '您已经开始这场考试',
                'exam_record' => $existingRecord,
                'exam_paper' => [
                    'id' => $examPaper->id,
                    'title' => $examPaper->title,
                    'total_time' => $examPaper->total_time,
                    'total_score' => $examPaper->total_score,
                    'scratch_paper_required' => (bool) $examPaper->scratch_paper_required,
                ],
                'scratch' => $existingState,
                'questions' => $existingState['can_answer']
                    ? $examPaper->questions()->get()->map(fn ($q) => [
                        'id' => $q->id,
                        'type' => $q->type,
                        'title' => $q->title,
                        'options' => $q->options,
                        'score' => $q->pivot->score,
                    ])->values()
                    : [],
            ]);
        }

        $record = ExamRecord::create([
            'user_id' => $request->user()->id,
            'exam_paper_id' => $examPaper->id,
            'start_time' => now(),
            'status' => 'in_progress',
        ]);

        $questions = $examPaper->questions()->get();

        $questionsData = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->pivot->score,
            ];
        });

        $scratchState = $this->scratchGuard->state($record, $examPaper);

        return response()->json([
            'message' => '考试开始',
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'total_score' => $examPaper->total_score,
                'scratch_paper_required' => (bool) $examPaper->scratch_paper_required,
            ],
            'scratch' => $scratchState,
            // 空白草稿纸照片未留存前不下发题目
            'questions' => $scratchState['can_answer'] ? $questionsData : [],
        ]);
    }

    public function getQuestions(Request $request, ExamPaper $examPaper)
    {
        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $scratchState = $this->scratchGuard->state($record, $examPaper);

        $questionsData = [];

        // 开考前必须先拍空白草稿纸（或经老师批准），否则不返回题目
        if ($scratchState['can_answer']) {
            $questions = $examPaper->questions()->get();

            $questionsData = $questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'type' => $q->type,
                    'title' => $q->title,
                    'options' => $q->options,
                    'score' => $q->pivot->score,
                ];
            });
        }

        return response()->json([
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'total_score' => $examPaper->total_score,
                'scratch_paper_required' => (bool) $examPaper->scratch_paper_required,
            ],
            'scratch' => $scratchState,
            'questions' => $questionsData,
        ]);
    }

    public function submit(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'present|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'present|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        // 草稿纸拍照留存闸门：交卷前必须有最终页照片（两阶段齐全），或经老师批准
        if (!$this->scratchGuard->canSubmit($record, $examPaper)) {
            $state = $this->scratchGuard->state($record, $examPaper);
            return response()->json([
                'message' => $state['waiver_status'] === 'pending'
                    ? '草稿纸照片不完整，已提交监考老师处理，请等待老师批准后再交卷'
                    : '请先完成草稿纸拍照（空白纸与最终页面）后再交卷，或申请交给老师处理',
                'error_code' => 'SCRATCH_PAPER_INCOMPLETE',
                'scratch' => $state,
            ], 422);
        }

        $submitTime = now();
        $totalScore = 0;
        $questionMap = $examPaper->questions->keyBy('id');

        foreach ($request->answers as $answerData) {
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
                'is_correct' => $isCorrect,
                'score' => $score,
                'answered_at' => $submitTime,
            ]);

            $totalScore += $score;
        }

        $record->update([
            'end_time' => $submitTime,
            'score' => $totalScore,
            'status' => 'graded',
        ]);

        return response()->json([
            'message' => '提交成功',
            'score' => $totalScore,
            'exam_record' => $record->load('answers'),
        ]);
    }

    public function myRecords(Request $request)
    {
        $records = ExamRecord::with('examPaper')
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
        $isStaff = in_array($user->role, ['admin', 'teacher'], true);

        if ($record->user_id !== $user->id && !$isStaff) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $record->load(['examPaper.questions', 'answers.question', 'scratchPaperPhotos', 'user', 'waiverHandler:id,username,real_name']);

        return response()->json([
            'record' => $record,
        ]);
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
}
