<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->longText('client_brief')->nullable();
            $table->longText('requirements')->nullable();
            $table->json('checklist')->nullable(); // required HTML elements / features
            $table->timestamp('deadline')->nullable();
            $table->integer('max_score')->default(100);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('class_id');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assignments');
    }
};
