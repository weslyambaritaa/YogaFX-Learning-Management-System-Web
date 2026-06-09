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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('access_tier_id')->constrained('access_tiers')->restrictOnDelete();
            $table->unsignedBigInteger('assessment_id')->nullable()->index();
            $table->string('title');
            $table->string('thumbnail');
            $table->string('workbook')->nullable();
            $table->string('video')->nullable();
            $table->string('audio')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
