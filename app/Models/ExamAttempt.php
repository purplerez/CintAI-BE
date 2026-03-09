<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id', 'student_id', 'score', 'status', 'question_order',
        'answers', 'activity_log', 'run_attempts', 'fullscreen_violated',
        'violations', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'question_order'      => 'array',
        'answers'             => 'array',
        'activity_log'        => 'array',
        'fullscreen_violated' => 'boolean',
        'started_at'          => 'datetime',
        'submitted_at'        => 'datetime',
        'score'               => 'float',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
