<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestCase extends Model
{
    protected $fillable = [
        'problem_id', 'input', 'expected_output',
        'is_sample', 'is_hidden', 'weight', 'order',
    ];

    protected $casts = [
        'is_sample' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(CodingProblem::class, 'problem_id');
    }
}
