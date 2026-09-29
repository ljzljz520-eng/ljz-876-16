<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_paper_id',
        'start_time',
        'end_time',
        'score',
        'status',
        'scratch_exception_status',
        'scratch_exception_reason',
        'scratch_exception_handled_by',
        'scratch_exception_handled_at',
        'scratch_exception_remark',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
        'scratch_exception_status' => 'string',
        'scratch_exception_handled_by' => 'integer',
        'scratch_exception_handled_at' => 'datetime',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';
    public const STATUS_SCRATCH_PENDING = 'scratch_pending';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
        self::STATUS_SCRATCH_PENDING => '草稿异常待处理',
    ];

    public const EXCEPTION_NONE = 'none';
    public const EXCEPTION_PENDING = 'pending';
    public const EXCEPTION_APPROVED = 'approved';
    public const EXCEPTION_REJECTED = 'rejected';

    public const EXCEPTION_STATUSES = [
        self::EXCEPTION_NONE => '无异常',
        self::EXCEPTION_PENDING => '待教师处理',
        self::EXCEPTION_APPROVED => '教师已放行',
        self::EXCEPTION_REJECTED => '教师已驳回',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamRecordAnswer::class, 'exam_record_id');
    }

    public function scratchPhotos()
    {
        return $this->hasMany(ExamScratchPhoto::class, 'exam_record_id')
            ->orderBy('taken_at');
    }

    public function exceptionHandler()
    {
        return $this->belongsTo(User::class, 'scratch_exception_handled_by');
    }

    /**
     * 本场考试是否需要草稿纸拍照
     */
    public function requiresScratchPhotos(): bool
    {
        return (bool) optional($this->examPaper)->allow_scratch_paper;
    }
}
