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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('question_text')->nullable();
            $table->string('question_type')->default('radio_buttons');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('show_instruction')->default(false);
            $table->longText('instruction_text')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('randomize_answers_order')->default(false);
            $table->boolean('jump_enabled')->default(false);
            $table->unsignedBigInteger('jump_to_question_id')->nullable();
            $table->boolean('show_maybe_answer')->default(true);
            $table->boolean('allow_multi_select')->default(false);
            $table->unsignedInteger('min_count')->nullable();
            $table->unsignedInteger('max_count')->nullable();
            $table->boolean('allow_other_option')->default(false);
            $table->boolean('show_labels')->default(true);
            $table->decimal('score_range_min', 10, 2)->nullable();
            $table->decimal('score_range_max', 10, 2)->nullable();
            $table->decimal('starting_score', 10, 2)->nullable();
            $table->unsignedInteger('section_count')->nullable();
            $table->boolean('allow_decimals')->default(false);
            $table->string('input_type')->nullable();
            $table->unsignedInteger('character_limit')->nullable();
            $table->boolean('show_score_tooltip')->default(false);
            $table->string('score_tooltip_format')->nullable();
            $table->string('answer_image_fit')->nullable();
            $table->unsignedInteger('answers_per_row')->nullable();
            $table->string('scoring_category')->default('overall_only');
            $table->string('left_label')->nullable();
            $table->string('center_label')->nullable();
            $table->string('right_label')->nullable();
            $table->timestamps();

            $table->foreign('jump_to_question_id')
                ->references('id')
                ->on('questions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
