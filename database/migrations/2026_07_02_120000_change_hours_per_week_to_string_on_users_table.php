<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('hours_per_week', 20)->nullable()->change();
        });

        DB::table('users')
            ->whereNotNull('hours_per_week')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $normalized = match ((string) $user->hours_per_week) {
                        '0-3', '0_3', '03', '3' => '0_3',
                        '4-7', '4_7', '47', '4', '5', '6' => '4_7',
                        '7-10', '7_10', '710', '7', '8', '9' => '7_10',
                        '10', '10+', '10_plus', '10plus' => '10_plus',
                        default => (string) $user->hours_per_week,
                    };

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['hours_per_week' => $normalized]);
                }
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('hours_per_week')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $normalized = match ((string) $user->hours_per_week) {
                        '0_3' => 3,
                        '4_7' => 4,
                        '7_10' => 7,
                        '10_plus' => 10,
                        default => is_numeric($user->hours_per_week)
                            ? (int) $user->hours_per_week
                            : null,
                    };

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['hours_per_week' => $normalized]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('hours_per_week')->nullable()->change();
        });
    }
};
