<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'class_id', 'created_by', 'title', 'description', 'duration_minutes',
        'max_score', 'randomize_questions', 'allow_run', 'fullscreen_required',
        'disable_copy_paste', 'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'randomize_questions' => 'boolean',
        'allow_run'           => 'boolean',
        'fullscreen_required' => 'boolean',
        'disable_copy_paste'  => 'boolean',
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'exam_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'exam_id');
    }
}
