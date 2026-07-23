<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->json('allowed_billing_days')->nullable()->after('fixed_billing_day');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('installment_billing_day')->nullable()->after('currency_code');
            $table->index('installment_billing_day');
        });

        Schema::table('payment_subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_day')->nullable()->after('next_billing_amount');
            $table->index('billing_day');
        });
    }

    public function down(): void
    {
        Schema::table('payment_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_day']);
            $table->dropColumn('billing_day');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropIndex(['installment_billing_day']);
            $table->dropColumn('installment_billing_day');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('allowed_billing_days');
        });
    }
};
