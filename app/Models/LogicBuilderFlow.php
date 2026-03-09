<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogicBuilderFlow extends Model
{
    protected $fillable = [
        'student_id', 'problem_id', 'title',
        'flow_data', 'generated_code', 'simulation_log',
    ];

    protected $casts = [
        'flow_data'      => 'array',
        'simulation_log' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(CodingProblem::class, 'problem_id');
    }
}
