<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('project_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('repository_url')->nullable();
            $table->string('zip_file_path')->nullable();
            $table->enum('status', ['pending', 'analyzing', 'analyzed', 'graded'])->default('pending');
            $table->json('analysis_result')->nullable(); // {has_index, semantic_tags, responsive, media_queries, folder_structure}
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index('assignment_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_submissions');
    }
};
