<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScratchPaperPhoto extends Model
{
    use HasFactory;

    public const PHASE_PRE = 'pre';
    public const PHASE_FINAL = 'final';

    public const PHASES = [
        self::PHASE_PRE => '开考前（空白草稿纸）',
        self::PHASE_FINAL => '交卷前（最终页面）',
    ];

    protected $fillable = [
        'exam_record_id',
        'user_id',
        'phase',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'elapsed_seconds',
        'client_captured_at',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'user_id' => 'integer',
        'file_size' => 'integer',
        'elapsed_seconds' => 'integer',
    ];

    protected $appends = [
        'phase_label',
    ];

    public function getPhaseLabelAttribute(): string
    {
        return self::PHASES[$this->phase] ?? $this->phase;
    }

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
