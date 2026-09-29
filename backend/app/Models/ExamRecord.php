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
        'scratch_waiver_status',
        'scratch_waiver_reason',
        'scratch_waiver_by',
        'scratch_waiver_at',
        'scratch_waiver_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
        'scratch_waiver_status' => 'string',
        'scratch_waiver_by' => 'integer',
        'scratch_waiver_at' => 'datetime',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
    ];

    public const WAIVER_NONE = 'none';
    public const WAIVER_PENDING = 'pending';
    public const WAIVER_APPROVED = 'approved';
    public const WAIVER_REJECTED = 'rejected';

    public const WAIVER_STATUSES = [
        self::WAIVER_NONE => '未申请',
        self::WAIVER_PENDING => '待老师处理',
        self::WAIVER_APPROVED => '老师已批准',
        self::WAIVER_REJECTED => '老师已拒绝',
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

    public function scratchPaperPhotos()
    {
        return $this->hasMany(ScratchPaperPhoto::class, 'exam_record_id')
            ->orderBy('elapsed_seconds')
            ->orderBy('id');
    }

    public function waiverHandler()
    {
        return $this->belongsTo(User::class, 'scratch_waiver_by');
    }
}
