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
            if (! Schema::hasColumn('access_tiers', 'payment_link')) {
                $table->string('payment_link')->nullable()->after('slug');
            }
        });

        DB::table('access_tiers')
            ->where('slug', 'online')
            ->update(['payment_link' => '/online']);

        DB::table('access_tiers')
            ->where('slug', 'starter_kit')
            ->update(['payment_link' => '/starter-kit']);

        DB::table('access_tiers')
            ->where('slug', 'master_class')
            ->update(['payment_link' => '/masterclass']);
    }

    public function down(): void
    {
        Schema::table('access_tiers', function (Blueprint $table) {
            if (Schema::hasColumn('access_tiers', 'payment_link')) {
                $table->dropColumn('payment_link');
            }
        });
    }
};
