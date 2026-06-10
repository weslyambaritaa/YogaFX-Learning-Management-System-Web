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
        Schema::create('assessment_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete()->unique();
            $table->string('logo')->nullable();
            $table->unsignedInteger('logo_max_width')->nullable();
            $table->string('logo_alignment')->nullable();
            $table->string('logo_link')->nullable();
            $table->string('header_position')->nullable();
            $table->string('section_background')->nullable();
            $table->unsignedInteger('top_margin')->nullable();
            $table->unsignedInteger('bottom_margin')->nullable();
            $table->text('footer_content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_designs');
    }
};
