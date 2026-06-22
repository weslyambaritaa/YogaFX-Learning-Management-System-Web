<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_activities')) {
            return;
        }

        $driver = DB::getDriverName();

        if (Schema::hasColumn('payment_activities', 'reference_code') && ! Schema::hasColumn('payment_activities', 'payment_reference')) {
            DB::statement('ALTER TABLE payment_activities RENAME COLUMN reference_code TO payment_reference');
        }

        if (Schema::hasColumn('payment_activities', 'amount') && ! Schema::hasColumn('payment_activities', 'amount_paid')) {
            DB::statement('ALTER TABLE payment_activities RENAME COLUMN amount TO amount_paid');
        }

        if ($driver !== 'sqlite' && Schema::hasColumn('payment_activities', 'amount_paid')) {
            DB::statement('ALTER TABLE payment_activities ALTER COLUMN amount_paid TYPE numeric(12,2)');
        }

        if ($driver !== 'sqlite' && Schema::hasColumn('payment_activities', 'payment_reference')) {
            DB::statement('ALTER TABLE payment_activities ALTER COLUMN payment_reference DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_activities')) {
            return;
        }

        if (Schema::hasColumn('payment_activities', 'payment_reference') && ! Schema::hasColumn('payment_activities', 'reference_code')) {
            DB::statement('ALTER TABLE payment_activities RENAME COLUMN payment_reference TO reference_code');
        }

        if (Schema::hasColumn('payment_activities', 'amount_paid') && ! Schema::hasColumn('payment_activities', 'amount')) {
            DB::statement('ALTER TABLE payment_activities RENAME COLUMN amount_paid TO amount');
        }
    }
};
