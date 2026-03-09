<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic');
            $table->integer('total_attempts')->default(0);
            $table->integer('accepted_count')->default(0);
            $table->decimal('avg_score', 5, 2)->default(0);
            $table->decimal('best_score', 5, 2)->default(0);
            $table->enum('level', ['beginner', 'developing', 'proficient', 'advanced', 'expert'])->default('beginner');
            $table->integer('xp')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('topic');
            $table->unique(['student_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_progress');
    }
};
