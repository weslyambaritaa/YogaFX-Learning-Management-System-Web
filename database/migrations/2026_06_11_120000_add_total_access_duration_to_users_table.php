<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'total_access_duration_seconds')) {
                $table->unsignedBigInteger('total_access_duration_seconds')
                    ->default(0)
                    ->after('access_tier_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'total_access_duration_seconds')) {
                $table->dropColumn('total_access_duration_seconds');
            }
        });
    }
};
