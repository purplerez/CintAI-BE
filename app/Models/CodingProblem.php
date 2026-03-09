<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CodingProblem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'class_id', 'created_by', 'title', 'description', 'starter_code',
        'difficulty', 'topic', 'max_score', 'time_limit_seconds',
        'memory_limit_mb', 'hints', 'is_published', 'detect_hardcode',
    ];

    protected $casts = [
        'hints'           => 'array',
        'is_published'    => 'boolean',
        'detect_hardcode' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class, 'problem_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'problem_id');
    }

    public function sampleTestCases(): HasMany
    {
        return $this->hasMany(TestCase::class, 'problem_id')->where('is_sample', true);
    }
}
