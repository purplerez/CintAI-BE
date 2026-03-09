<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coding_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->longText('starter_code')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->string('topic')->nullable();
            $table->integer('max_score')->default(100);
            $table->integer('time_limit_seconds')->default(3);
            $table->integer('memory_limit_mb')->default(64);
            $table->json('hints')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('detect_hardcode')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('class_id');
            $table->index('topic');
            $table->index('difficulty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coding_problems');
    }
};
