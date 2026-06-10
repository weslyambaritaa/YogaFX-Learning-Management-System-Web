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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('scoring_mode')->default('points');
            $table->string('result_mode')->default('score_or_range');
            $table->boolean('is_active')->default(false);
            $table->boolean('show_progress_bar')->default(true);
            $table->boolean('allow_back_navigation')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
