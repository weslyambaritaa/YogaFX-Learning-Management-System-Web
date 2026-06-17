<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('pending_registration_id')
                ->nullable()
                ->constrained('pending_registrations')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('access_tier_id')
                ->constrained('access_tiers')
                ->cascadeOnDelete();
            $table->string('context')->default('initial')->index();
            $table->string('payment_type')->index();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->string('status')->default('pending')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
