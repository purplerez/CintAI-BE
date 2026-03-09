<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSubmission extends Model
{
    protected $fillable = [
        'assignment_id', 'student_id', 'repository_url', 'zip_file_path',
        'status', 'analysis_result', 'score', 'feedback', 'submitted_at',
    ];

    protected $casts = [
        'analysis_result' => 'array',
        'submitted_at'    => 'datetime',
        'score'           => 'float',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssignment::class, 'assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
