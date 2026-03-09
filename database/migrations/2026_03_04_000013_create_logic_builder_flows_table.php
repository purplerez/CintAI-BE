<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logic_builder_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('problem_id')->nullable()->constrained('coding_problems')->nullOnDelete();
            $table->string('title')->default('Untitled Flow');
            $table->json('flow_data'); // ReactFlow nodes + edges JSON
            $table->longText('generated_code')->nullable(); // generated C++ code
            $table->json('simulation_log')->nullable(); // step-by-step execution trace
            $table->timestamps();

            $table->index('student_id');
            $table->index('problem_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logic_builder_flows');
    }
};
