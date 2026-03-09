<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillProgress extends Model
{
    protected $fillable = [
        'student_id', 'topic', 'total_attempts', 'accepted_count',
        'avg_score', 'best_score', 'level', 'xp', 'last_activity_at',
    ];

    protected $casts = [
        'avg_score'        => 'float',
        'best_score'       => 'float',
        'last_activity_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
