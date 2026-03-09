<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->enum('status', ['in_progress', 'submitted', 'auto_submitted', 'timed_out'])->default('in_progress');
            $table->json('question_order')->nullable(); // randomized problem IDs
            $table->json('answers')->nullable(); // {problem_id: submission_id}
            $table->json('activity_log')->nullable(); // tab_switch, blur, run events
            $table->integer('run_attempts')->default(0);
            $table->boolean('fullscreen_violated')->default(false);
            $table->integer('violations')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('exam_id');
            $table->index('student_id');
            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
