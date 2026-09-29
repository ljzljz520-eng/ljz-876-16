<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ScratchPaperPhoto;
use App\Support\ScratchPaperGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScratchPaperController extends Controller
{
    public function __construct(protected ScratchPaperGuard $guard)
    {
    }

    /**
     * 获取当前进行中考试的草稿纸留存状态（学生考试页用）
     */
    public function status(Request $request, ExamPaper $examPaper)
    {
        $record = $this->findInProgressRecord($request, $examPaper);

        return response()->json([
            'required' => (bool) $examPaper->scratch_paper_required,
            'exam_record' => $record,
            'scratch' => $this->guard->state($record, $examPaper),
        ]);
    }

    /**
     * 上传草稿纸照片（开考前空白纸 / 交卷前最终页）
     */
    public function upload(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'phase' => 'required|in:pre,final',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
            'client_captured_at' => 'nullable|string|max:30',
        ], [
            'phase.required' => '缺少拍摄阶段参数',
            'photo.required' => '请先拍摄草稿纸照片',
            'photo.image' => '上传内容必须是图片',
            'photo.mimes' => '仅支持 JPG / PNG / WEBP 格式照片',
            'photo.max' => '照片大小不能超过 10MB',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = $this->findInProgressRecord($request, $examPaper);
        $phase = $request->input('phase');

        $file = $request->file('photo');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $path = $file->storeAs(
            'scratch-papers/' . $record->id,
            $phase . '_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $extension,
            'local'
        );

        $photo = ScratchPaperPhoto::create([
            'exam_record_id' => $record->id,
            'user_id' => $request->user()->id,
            'phase' => $phase,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize() ?: 0,
            'mime_type' => $file->getClientMimeType(),
            'elapsed_seconds' => abs(now()->diffInSeconds($record->start_time)),
            'client_captured_at' => $request->input('client_captured_at'),
        ]);

        return response()->json([
            'message' => $phase === ScratchPaperPhoto::PHASE_PRE ? '空白草稿纸照片已留存' : '最终页面照片已留存',
            'photo' => $this->presentPhoto($photo),
            'scratch' => $this->guard->state($record->fresh(), $examPaper),
        ], 201);
    }

    /**
     * 学生漏拍时申请“交给老师处理”（免拍/补交）
     */
    public function requestWaiver(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:2|max:500',
        ], [
            'reason.required' => '请填写无法拍摄/漏拍原因，便于监考老师处理',
            'reason.min' => '原因说明至少 2 个字符',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = $this->findInProgressRecord($request, $examPaper);

        if (!$examPaper->scratch_paper_required) {
            return response()->json(['message' => '本场考试无需草稿纸拍照'], 422);
        }

        if ($record->scratch_waiver_status === ExamRecord::WAIVER_APPROVED) {
            return response()->json(['message' => '老师已批准，无需重复申请'], 422);
        }

        $record->update([
            'scratch_waiver_status' => ExamRecord::WAIVER_PENDING,
            'scratch_waiver_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'message' => '已提交监考老师处理，请等待老师批准后再交卷',
            'scratch' => $this->guard->state($record->fresh(), $examPaper),
        ]);
    }

    /**
     * 监考回放时间轴（学生本人或教师/管理员可访问）
     */
    public function timeline(Request $request, ExamPaper $examPaper, ExamRecord $record)
    {
        $this->assertCanViewRecord($request->user(), $record);

        if ($record->exam_paper_id !== $examPaper->id) {
            abort(404);
        }

        $record->load(['answers.question', 'scratchPaperPhotos', 'user:id,username,real_name', 'waiverHandler:id,username,real_name']);

        $events = [];

        $events[] = [
            'type' => 'exam_start',
            'label' => '考试开始',
            'elapsed_seconds' => 0,
            'at' => optional($record->start_time)->toIso8601String(),
        ];

        foreach ($record->scratchPaperPhotos as $photo) {
            $events[] = [
                'type' => 'scratch_photo',
                'phase' => $photo->phase,
                'label' => $photo->phase === ScratchPaperPhoto::PHASE_PRE ? '空白草稿纸拍照' : '最终页面拍照',
                'elapsed_seconds' => $photo->elapsed_seconds,
                'at' => optional($photo->created_at)->toIso8601String(),
                'photo' => $this->presentPhoto($photo),
            ];
        }

        foreach ($record->answers as $answer) {
            $events[] = [
                'type' => 'answer',
                'label' => '提交答案：' . mb_substr($answer->question->title ?? ('题目#' . $answer->question_id), 0, 20),
                'question_id' => $answer->question_id,
                'answer' => $answer->answer,
                'is_correct' => (bool) $answer->is_correct,
                'score' => (float) $answer->score,
                'elapsed_seconds' => $answer->answered_at
                    ? abs($answer->answered_at->diffInSeconds($record->start_time))
                    : null,
                'at' => optional($answer->answered_at ?? $answer->created_at)->toIso8601String(),
            ];
        }

        if ($record->end_time) {
            $events[] = [
                'type' => 'exam_end',
                'label' => '交卷',
                'elapsed_seconds' => abs($record->end_time->diffInSeconds($record->start_time)),
                'at' => $record->end_time->toIso8601String(),
            ];
        }

        usort($events, function ($a, $b) {
            $ta = $a['elapsed_seconds'] ?? -1;
            $tb = $b['elapsed_seconds'] ?? -1;
            if ($ta === $tb) {
                return strcmp($a['at'] ?? '', $b['at'] ?? '');
            }
            return $ta <=> $tb;
        });

        return response()->json([
            'record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'scratch_paper_required' => (bool) $examPaper->scratch_paper_required,
            ],
            'events' => $events,
            'photos' => $record->scratchPaperPhotos->map(fn ($p) => $this->presentPhoto($p))->values(),
        ]);
    }

    /**
     * 教师/管理员：待处理与全部需复核的考试记录（含草稿留存情况）
     */
    public function reviewList(Request $request)
    {
        $this->assertStaff($request);

        $query = ExamRecord::with(['user:id,username,real_name', 'examPaper:id,title,scratch_paper_required'])
            ->whereHas('examPaper', fn ($q) => $q->where('scratch_paper_required', true))
            ->orderByRaw("FIELD(scratch_waiver_status, 'pending', 'rejected', 'none', 'approved')")
            ->orderBy('id', 'desc');

        if ($request->filled('waiver_status')) {
            $query->where('scratch_waiver_status', $request->input('waiver_status'));
        }

        $records = $query->paginate($request->input('per_page', 15));

        $records->getCollection()->transform(function (ExamRecord $record) {
            $record->setAttribute('scratch_pre_count', $this->guard->preCount($record));
            $record->setAttribute('scratch_final_count', $this->guard->finalCount($record));
            return $record;
        });

        return response()->json([
            'records' => $records,
            'pending_count' => ExamRecord::where('scratch_waiver_status', ExamRecord::WAIVER_PENDING)->count(),
        ]);
    }

    /**
     * 教师/管理员：处理学生的免拍申请（批准 / 拒绝）
     */
    public function reviewWaiver(Request $request, ExamRecord $record)
    {
        $this->assertStaff($request);

        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($record->scratch_waiver_status !== ExamRecord::WAIVER_PENDING) {
            return response()->json(['message' => '该申请已处理或不存在'], 422);
        }

        $record->update([
            'scratch_waiver_status' => $request->input('decision'),
            'scratch_waiver_note' => $request->input('note'),
            'scratch_waiver_by' => $request->user()->id,
            'scratch_waiver_at' => now(),
        ]);

        return response()->json([
            'message' => $request->input('decision') === ExamRecord::WAIVER_APPROVED
                ? '已批准，学生可继续交卷'
                : '已驳回申请',
            'record' => $record->fresh()->load('waiverHandler:id,username,real_name'),
        ]);
    }

    /**
     * 下载草稿纸照片（本人或教师/管理员），支持 ?token= 以兼容 <img> 标签
     */
    public function download(Request $request, ScratchPaperPhoto $photo)
    {
        $photo->load('examRecord');
        $this->assertCanViewRecord($request->user(), $photo->examRecord);

        if (!Storage::disk('local')->exists($photo->file_path)) {
            abort(404, '照片文件不存在');
        }

        return new StreamedResponse(function () use ($photo) {
            $stream = Storage::disk('local')->readStream($photo->file_path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $photo->mime_type ?: 'image/jpeg',
            'Content-Length' => Storage::disk('local')->size($photo->file_path),
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline; filename="scratch-' . $photo->phase . '-' . $photo->id . '.jpg"',
        ]);
    }

    protected function findInProgressRecord(Request $request, ExamPaper $examPaper): ExamRecord
    {
        return ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', ExamRecord::STATUS_IN_PROGRESS)
            ->firstOrFail();
    }

    protected function assertCanViewRecord($user, ExamRecord $record): void
    {
        $isOwner = $record->user_id === $user->id;
        $isStaff = in_array($user->role, ['admin', 'teacher'], true);

        if (!$isOwner && !$isStaff) {
            abort(403, '无权查看此草稿纸记录');
        }
    }

    protected function assertStaff(Request $request): void
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'], true)) {
            abort(403, '仅监考教师/管理员可访问');
        }
    }

    protected function presentPhoto(ScratchPaperPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'phase' => $photo->phase,
            'phase_label' => $photo->phase_label,
            'url' => url('/api/scratch-papers/photos/' . $photo->id),
            'elapsed_seconds' => $photo->elapsed_seconds,
            'client_captured_at' => $photo->client_captured_at,
            'created_at' => optional($photo->created_at)->toIso8601String(),
            'file_size' => $photo->file_size,
        ];
    }
}
