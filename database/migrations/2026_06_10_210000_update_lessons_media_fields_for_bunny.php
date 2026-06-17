<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lessons RENAME COLUMN video TO lesson_video_id');
        DB::statement('ALTER TABLE lessons RENAME COLUMN audio TO audio_url');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lessons RENAME COLUMN lesson_video_id TO video');
        DB::statement('ALTER TABLE lessons RENAME COLUMN audio_url TO audio');
    }
};
