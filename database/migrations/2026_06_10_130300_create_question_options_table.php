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
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('label');
            $table->string('internal_value')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('scoring_enabled')->default(false);
            $table->decimal('score_value', 10, 2)->nullable();
            $table->boolean('jump_enabled')->default(false);
            $table->unsignedBigInteger('jump_to_question_id')->nullable();
            $table->boolean('is_other_option')->default(false);
            $table->boolean('is_fixed_option')->default(false);
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
        Schema::dropIfExists('question_options');
    }
};
