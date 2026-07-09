<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            if (! Schema::hasColumn('lesson_progress', 'watch_time_seconds')) {
                $table->unsignedInteger('watch_time_seconds')
                    ->default(0)
                    ->after('watch_progress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            if (Schema::hasColumn('lesson_progress', 'watch_time_seconds')) {
                $table->dropColumn('watch_time_seconds');
            }
        });
    }
};
