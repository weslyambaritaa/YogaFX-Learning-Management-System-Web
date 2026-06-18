<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_tiers', function (Blueprint $table) {
            $table->renameColumn('price_amount', 'price');
        });

        Schema::table('access_tiers', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->change();
            $table->string('currency_code', 3)->default('USD')->after('price');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->decimal('amount_snapshot', 12, 2)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('context', 'type');
            $table->renameColumn('balance_amount', 'balance_due');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->change();
            $table->decimal('balance_due', 12, 2)->change();
            $table->string('currency_code', 3)->default('USD')->after('balance_due');
        });

        Schema::rename('payment_activities', 'payments');

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('reference_code', 'payment_reference');
            $table->renameColumn('amount', 'amount_paid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->change();
            $table->string('currency_code', 3)->default('USD')->after('amount_paid');
            $table->text('notes')->nullable()->after('payment_reference');
        });

        Schema::create('certificate_download_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('downloaded_at');
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_download_events');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'notes']);
            $table->renameColumn('payment_reference', 'reference_code');
            $table->renameColumn('amount_paid', 'amount');
        });

        Schema::rename('payments', 'payment_activities');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('currency_code');
            $table->renameColumn('type', 'context');
            $table->renameColumn('balance_due', 'balance_amount');
        });

        Schema::table('access_tiers', function (Blueprint $table) {
            $table->dropColumn('currency_code');
            $table->renameColumn('price', 'price_amount');
        });
    }
};
