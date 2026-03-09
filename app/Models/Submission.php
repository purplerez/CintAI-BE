<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $fillable = [
        'student_id', 'problem_id', 'code', 'language', 'status',
        'score', 'passed_cases', 'total_cases', 'execution_time_ms',
        'memory_used_kb', 'test_case_results', 'logic_analysis',
        'compile_error', 'is_exam', 'exam_id',
    ];

    protected $casts = [
        'test_case_results' => 'array',
        'logic_analysis'    => 'array',
        'is_exam'           => 'boolean',
        'score'             => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(CodingProblem::class, 'problem_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }
}
