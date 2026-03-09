<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained('coding_problems')->cascadeOnDelete();
            $table->text('input')->nullable();
            $table->text('expected_output');
            $table->boolean('is_sample')->default(false); // visible to students
            $table->boolean('is_hidden')->default(false); // anti-cheat
            $table->integer('weight')->default(1); // scoring weight
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('problem_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
