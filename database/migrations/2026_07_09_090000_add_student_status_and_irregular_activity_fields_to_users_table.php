<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 20)
                    ->default('available')
                    ->after('is_active')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'irregular_activity_count')) {
                $table->unsignedInteger('irregular_activity_count')
                    ->default(0)
                    ->after('account_status');
            }

            if (! Schema::hasColumn('users', 'irregular_activity_last_detected_at')) {
                $table->timestamp('irregular_activity_last_detected_at')
                    ->nullable()
                    ->after('irregular_activity_count');
            }
        });

        DB::table('users')
            ->where('role', 'student')
            ->update([
                'account_status' => DB::raw("CASE WHEN is_active = true THEN 'available' ELSE 'inactive' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'irregular_activity_last_detected_at')) {
                $table->dropColumn('irregular_activity_last_detected_at');
            }

            if (Schema::hasColumn('users', 'irregular_activity_count')) {
                $table->dropColumn('irregular_activity_count');
            }

            if (Schema::hasColumn('users', 'account_status')) {
                $table->dropColumn('account_status');
            }
        });
    }
};
