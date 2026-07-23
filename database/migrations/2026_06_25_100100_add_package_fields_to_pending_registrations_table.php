<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->after('access_tier_id')
                ->constrained('packages')
                ->nullOnDelete();
            $table->string('currency_code', 3)
                ->nullable()
                ->after('amount_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn('currency_code');
        });
    }
};
