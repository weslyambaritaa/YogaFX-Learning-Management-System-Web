<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasTable('payment_activities')) {
            Schema::rename('payments', 'payment_activities');
        }

        if (Schema::hasTable('invoices')) {
            DB::table('invoices')
                ->where('status', 'pending')
                ->update(['status' => 'unpaid']);
        }

        if (Schema::hasTable('payment_activities')) {
            Schema::table('payment_activities', function (Blueprint $table): void {
                if (Schema::hasColumn('payment_activities', 'pending_registration_id')) {
                    $table->dropConstrainedForeignId('pending_registration_id');
                }

                if (Schema::hasColumn('payment_activities', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }

                if (Schema::hasColumn('payment_activities', 'processed_at')) {
                    $table->dropColumn('processed_at');
                }

                if (Schema::hasColumn('payment_activities', 'meta')) {
                    $table->dropColumn('meta');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_activities')) {
            Schema::table('payment_activities', function (Blueprint $table): void {
                if (! Schema::hasColumn('payment_activities', 'pending_registration_id')) {
                    $table->foreignId('pending_registration_id')
                        ->nullable()
                        ->after('invoice_id')
                        ->constrained('pending_registrations')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('payment_activities', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('pending_registration_id')
                        ->constrained()
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('payment_activities', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable()->after('status');
                }

                if (! Schema::hasColumn('payment_activities', 'meta')) {
                    $table->json('meta')->nullable()->after('processed_at');
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            DB::table('invoices')
                ->where('status', 'unpaid')
                ->update(['status' => 'pending']);
        }

        if (Schema::hasTable('payment_activities') && ! Schema::hasTable('payments')) {
            Schema::rename('payment_activities', 'payments');
        }
    }
};
