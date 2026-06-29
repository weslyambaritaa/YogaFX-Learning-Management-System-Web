<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'allowed_billing_days')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->json('allowed_billing_days')->nullable()->after('fixed_billing_day');
            });
        }

        if (Schema::hasTable('pending_registrations') && ! Schema::hasColumn('pending_registrations', 'installment_billing_day')) {
            Schema::table('pending_registrations', function (Blueprint $table): void {
                $table->unsignedTinyInteger('installment_billing_day')->nullable()->after('currency_code');
            });
        }

        if (Schema::hasTable('payment_subscriptions') && ! Schema::hasColumn('payment_subscriptions', 'billing_day')) {
            Schema::table('payment_subscriptions', function (Blueprint $table): void {
                $table->unsignedTinyInteger('billing_day')->nullable()->after('next_billing_amount');
            });
        }

        $this->ensurePgsqlIndex(
            table: 'pending_registrations',
            index: 'pending_registrations_installment_billing_day_index',
            expression: '(installment_billing_day)',
            requiredColumn: 'installment_billing_day',
        );

        $this->ensurePgsqlIndex(
            table: 'payment_subscriptions',
            index: 'payment_subscriptions_billing_day_index',
            expression: '(billing_day)',
            requiredColumn: 'billing_day',
        );
    }

    public function down(): void
    {
        // Repair migration only. No destructive rollback to avoid removing
        // live columns that may already be depended on in existing environments.
    }

    private function ensurePgsqlIndex(
        string $table,
        string $index,
        string $expression,
        string $requiredColumn,
    ): void {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $requiredColumn)) {
            return;
        }

        $currentSchema = (string) DB::scalar('select current_schema()');

        $exists = DB::table('pg_indexes')
            ->where('schemaname', $currentSchema)
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->exists();

        if (! $exists) {
            DB::statement(sprintf(
                'create index %s on %s using btree %s',
                $index,
                $table,
                $expression,
            ));
        }
    }
};
