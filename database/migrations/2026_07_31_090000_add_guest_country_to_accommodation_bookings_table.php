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
        Schema::table('accommodation_bookings', function (Blueprint $table) {
            $table->string('guest_country')->nullable()->after('guest_phone');
        });

        // Backfill any bookings that already exist (this domain is live)
        // before tightening the column to NOT NULL below.
        DB::table('accommodation_bookings')
            ->whereNull('guest_country')
            ->update(['guest_country' => 'Unknown']);

        Schema::table('accommodation_bookings', function (Blueprint $table) {
            $table->string('guest_country')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accommodation_bookings', function (Blueprint $table) {
            $table->dropColumn('guest_country');
        });
    }
};
