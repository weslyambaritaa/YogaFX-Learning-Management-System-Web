<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'installment_deadline_date')) {
                $table->date('installment_deadline_date')
                    ->nullable()
                    ->after('installment_enabled');
            }

            if (! Schema::hasColumn('packages', 'allowed_billing_days')) {
                $table->json('allowed_billing_days')
                    ->nullable()
                    ->after('installment_deadline_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'allowed_billing_days')) {
                $table->dropColumn('allowed_billing_days');
            }

            if (Schema::hasColumn('packages', 'installment_deadline_date')) {
                $table->dropColumn('installment_deadline_date');
            }
        });
    }
};