<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('problem_id')->constrained('coding_problems')->cascadeOnDelete();
            $table->longText('code');
            $table->string('language')->default('cpp');
            $table->enum('status', ['pending', 'queued', 'compiling', 'running', 'accepted', 'wrong_answer', 'time_limit', 'memory_limit', 'compile_error', 'runtime_error'])->default('pending');
            $table->decimal('score', 5, 2)->default(0);
            $table->integer('passed_cases')->default(0);
            $table->integer('total_cases')->default(0);
            $table->integer('execution_time_ms')->nullable();
            $table->integer('memory_used_kb')->nullable();
            $table->json('test_case_results')->nullable(); // [{case_id, passed, output, expected}]
            $table->json('logic_analysis')->nullable(); // hardcode detection results
            $table->text('compile_error')->nullable();
            $table->boolean('is_exam')->default(false);
            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id');
            $table->index('problem_id');
            $table->index('status');
            $table->index(['student_id', 'problem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
