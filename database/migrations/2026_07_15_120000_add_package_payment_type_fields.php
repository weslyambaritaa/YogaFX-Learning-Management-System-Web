<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->string('payment_type')
                ->default('paid')
                ->after('description');
            $table->decimal('minimum_donation_amount', 12, 2)
                ->nullable()
                ->after('price');
            $table->decimal('suggested_donation_amount', 12, 2)
                ->nullable()
                ->after('minimum_donation_amount');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('package_payment_type')
                ->nullable()
                ->after('payment_type');
        });

        Schema::table('payment_activities', function (Blueprint $table): void {
            $table->string('package_payment_type')
                ->nullable()
                ->after('payment_type');
        });

        DB::table('packages')->update([
            'payment_type' => 'paid',
            'minimum_donation_amount' => null,
            'suggested_donation_amount' => null,
        ]);

        DB::table('invoices')
            ->whereNotNull('package_id')
            ->update([
                'package_payment_type' => 'paid',
            ]);

        DB::table('payment_activities')
            ->whereNotNull('invoice_id')
            ->update([
                'package_payment_type' => 'paid',
            ]);
    }

    public function down(): void
    {
        Schema::table('payment_activities', function (Blueprint $table): void {
            $table->dropColumn('package_payment_type');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('package_payment_type');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_type',
                'minimum_donation_amount',
                'suggested_donation_amount',
            ]);
        });
    }
};
