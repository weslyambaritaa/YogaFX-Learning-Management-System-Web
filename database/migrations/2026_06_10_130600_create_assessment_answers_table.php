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
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->longText('answer_text')->nullable();
            $table->decimal('answer_number', 10, 2)->nullable();
            $table->boolean('answer_boolean')->nullable();
            $table->decimal('score_awarded', 10, 2)->nullable();
            $table->boolean('is_final')->default(true);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['assessment_attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
