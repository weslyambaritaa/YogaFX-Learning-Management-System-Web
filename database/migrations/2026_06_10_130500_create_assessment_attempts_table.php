<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('current_question_id')->nullable();
            $table->unsignedBigInteger('last_answered_question_id')->nullable();
            $table->decimal('total_score', 10, 2)->default(0);
            $table->unsignedBigInteger('result_range_id')->nullable();
            $table->string('result_label')->nullable();
            $table->string('finished_reason')->nullable();
            $table->timestamps();

            $table->foreign('current_question_id')
                ->references('id')
                ->on('questions')
                ->nullOnDelete();
            $table->foreign('last_answered_question_id')
                ->references('id')
                ->on('questions')
                ->nullOnDelete();
            $table->foreign('result_range_id')
                ->references('id')
                ->on('assessment_result_ranges')
                ->nullOnDelete();

            $table->unique(['user_id', 'assessment_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
