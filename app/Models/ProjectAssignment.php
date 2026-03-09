<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'class_id', 'created_by', 'title', 'client_brief',
        'requirements', 'checklist', 'deadline', 'max_score', 'is_published',
    ];

    protected $casts = [
        'checklist'    => 'array',
        'deadline'     => 'datetime',
        'is_published' => 'boolean',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class, 'assignment_id');
    }
}
