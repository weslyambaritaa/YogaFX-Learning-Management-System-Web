<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->string('review_token', 64)->nullable()->after('reviewed_by');
        });

        DB::table('assignment_submissions')
            ->whereNull('review_token')
            ->orderBy('id')
            ->chunkById(100, function ($submissions): void {
                foreach ($submissions as $submission) {
                    do {
                        $token = Str::random(64);
                    } while (
                        DB::table('assignment_submissions')
                            ->where('review_token', $token)
                            ->exists()
                    );

                    DB::table('assignment_submissions')
                        ->where('id', $submission->id)
                        ->update([
                            'review_token' => $token,
                        ]);
                }
            });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->unique('review_token');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropUnique(['review_token']);
            $table->dropColumn('review_token');
        });
    }
};  