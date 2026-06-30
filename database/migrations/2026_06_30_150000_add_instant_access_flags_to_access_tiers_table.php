<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_tiers', function (Blueprint $table) {
            $table->boolean('has_full_standing_dialog_access')
                ->default(false)
                ->after('payment_link');
            $table->boolean('has_full_floor_dialog_access')
                ->default(false)
                ->after('has_full_standing_dialog_access');
        });

        DB::table('access_tiers')->update([
            'has_full_standing_dialog_access' => true,
            'has_full_floor_dialog_access' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('access_tiers', function (Blueprint $table) {
            $table->dropColumn([
                'has_full_standing_dialog_access',
                'has_full_floor_dialog_access',
            ]);
        });
    }
};
