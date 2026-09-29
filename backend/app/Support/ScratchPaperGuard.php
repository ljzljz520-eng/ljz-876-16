<?php

namespace App\Support;

use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ScratchPaperPhoto;

/**
 * 草稿纸拍照留存准入规则：
 * - 不要求草稿纸的试卷：直接放行
 * - 开考前需有空白纸照片（pre），否则只能等待老师批准免拍
 * - 交卷前需有最终页面照片（final），两阶段齐全或老师批准才可交卷
 */
class ScratchPaperGuard
{
    public function preCount(ExamRecord $record): int
    {
        return (int) ScratchPaperPhoto::where('exam_record_id', $record->id)
            ->where('phase', ScratchPaperPhoto::PHASE_PRE)
            ->count();
    }

    public function finalCount(ExamRecord $record): int
    {
        return (int) ScratchPaperPhoto::where('exam_record_id', $record->id)
            ->where('phase', ScratchPaperPhoto::PHASE_FINAL)
            ->count();
    }

    public function waiverApproved(ExamRecord $record): bool
    {
        return $record->scratch_waiver_status === ExamRecord::WAIVER_APPROVED;
    }

    public function canAnswer(ExamRecord $record, ExamPaper $paper): bool
    {
        if (!$paper->scratch_paper_required) {
            return true;
        }
        return $this->preCount($record) > 0 || $this->waiverApproved($record);
    }

    public function canSubmit(ExamRecord $record, ExamPaper $paper): bool
    {
        if (!$paper->scratch_paper_required) {
            return true;
        }
        return ($this->preCount($record) > 0 && $this->finalCount($record) > 0)
            || $this->waiverApproved($record);
    }

    public function state(ExamRecord $record, ExamPaper $paper): array
    {
        $required = (bool) $paper->scratch_paper_required;
        $preCount = $required ? $this->preCount($record) : 0;
        $finalCount = $required ? $this->finalCount($record) : 0;
        $waiverApproved = $this->waiverApproved($record);

        $photos = $required
            ? ScratchPaperPhoto::where('exam_record_id', $record->id)
                ->orderBy('elapsed_seconds')
                ->orderBy('id')
                ->get()
                ->map(fn (ScratchPaperPhoto $p) => $this->presentPhoto($p))
                ->values()
            : collect();

        return [
            'required' => $required,
            'pre_photo_taken' => $preCount > 0,
            'final_photo_taken' => $finalCount > 0,
            'pre_photo_count' => $preCount,
            'final_photo_count' => $finalCount,
            'waiver_status' => $record->scratch_waiver_status ?? ExamRecord::WAIVER_NONE,
            'waiver_status_label' => ExamRecord::WAIVER_STATUSES[$record->scratch_waiver_status ?? ExamRecord::WAIVER_NONE] ?? '未申请',
            'waiver_reason' => $record->scratch_waiver_reason,
            'waiver_note' => $record->scratch_waiver_note,
            'can_answer' => $this->canAnswer($record, $paper),
            'can_submit' => $this->canSubmit($record, $paper),
            'missing' => [
                'pre' => $required && $preCount === 0 && !$waiverApproved,
                'final' => $required && $finalCount === 0 && !$waiverApproved,
            ],
            'photos' => $photos,
        ];
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
