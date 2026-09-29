<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamScratchPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'user_id',
        'phase',
        'photo_path',
        'taken_at',
        'server_time',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'user_id' => 'integer',
        'taken_at' => 'datetime',
        'server_time' => 'datetime',
    ];

    public const PHASE_BEFORE_START = 'before_start';
    public const PHASE_BEFORE_SUBMIT = 'before_submit';

    public const PHASES = [
        self::PHASE_BEFORE_START => '开考前空白页',
        self::PHASE_BEFORE_SUBMIT => '交卷前最终页',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
