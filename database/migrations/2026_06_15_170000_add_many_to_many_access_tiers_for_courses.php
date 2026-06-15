<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_tier_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_tier_id')->constrained('access_tiers')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['access_tier_id', 'course_id']);
        });

        DB::table('courses')
            ->whereNotNull('access_tier_id')
            ->orderBy('id')
            ->get(['id', 'access_tier_id'])
            ->each(function (object $course): void {
                DB::table('access_tier_course')->insert([
                    'access_tier_id' => $course->access_tier_id,
                    'course_id' => $course->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_tier_course');
    }
};
