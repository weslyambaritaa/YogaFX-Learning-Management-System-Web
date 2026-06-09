<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('access_tier_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_tier_id')->constrained('access_tiers')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['access_tier_id', 'module_id']);
        });

        Schema::create('access_tier_lesson', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_tier_id')->constrained('access_tiers')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['access_tier_id', 'lesson_id']);
        });

        Schema::create('access_tier_ebook', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_tier_id')->constrained('access_tiers')->cascadeOnDelete();
            $table->foreignId('ebook_id')->constrained('ebooks')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['access_tier_id', 'ebook_id']);
        });

        Schema::table('ebooks', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(1)->after('file');
        });

        $now = now();

        DB::table('modules')
            ->whereNotNull('access_tier_id')
            ->orderBy('id')
            ->get(['id', 'access_tier_id'])
            ->each(function (object $module) use ($now): void {
                DB::table('access_tier_module')->insert([
                    'access_tier_id' => $module->access_tier_id,
                    'module_id' => $module->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('lessons')
            ->whereNotNull('access_tier_id')
            ->orderBy('id')
            ->get(['id', 'access_tier_id'])
            ->each(function (object $lesson) use ($now): void {
                DB::table('access_tier_lesson')->insert([
                    'access_tier_id' => $lesson->access_tier_id,
                    'lesson_id' => $lesson->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('ebooks')
            ->whereNotNull('access_tier_id')
            ->orderBy('id')
            ->get(['id', 'access_tier_id'])
            ->each(function (object $ebook, int $index) use ($now): void {
                DB::table('access_tier_ebook')->insert([
                    'access_tier_id' => $ebook->access_tier_id,
                    'ebook_id' => $ebook->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('ebooks')
                    ->where('id', $ebook->id)
                    ->update(['sort_order' => $index + 1]);
            });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_tier_id');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_tier_id');
        });

        Schema::table('ebooks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_tier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('access_tier_id')->nullable()->after('thumbnail')->constrained('access_tiers')->restrictOnDelete();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('access_tier_id')->nullable()->after('module_id')->constrained('access_tiers')->restrictOnDelete();
        });

        Schema::table('ebooks', function (Blueprint $table) {
            $table->foreignId('access_tier_id')->nullable()->after('file')->constrained('access_tiers')->restrictOnDelete();
        });

        DB::table('modules')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $module): void {
                $tierId = DB::table('access_tier_module')
                    ->where('module_id', $module->id)
                    ->orderBy('id')
                    ->value('access_tier_id');

                DB::table('modules')
                    ->where('id', $module->id)
                    ->update(['access_tier_id' => $tierId]);
            });

        DB::table('lessons')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $lesson): void {
                $tierId = DB::table('access_tier_lesson')
                    ->where('lesson_id', $lesson->id)
                    ->orderBy('id')
                    ->value('access_tier_id');

                DB::table('lessons')
                    ->where('id', $lesson->id)
                    ->update(['access_tier_id' => $tierId]);
            });

        DB::table('ebooks')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $ebook): void {
                $tierId = DB::table('access_tier_ebook')
                    ->where('ebook_id', $ebook->id)
                    ->orderBy('id')
                    ->value('access_tier_id');

                DB::table('ebooks')
                    ->where('id', $ebook->id)
                    ->update(['access_tier_id' => $tierId]);
            });

        Schema::table('ebooks', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::dropIfExists('access_tier_module');
        Schema::dropIfExists('access_tier_lesson');
        Schema::dropIfExists('access_tier_ebook');
    }
};
