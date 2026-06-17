<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();
            $table->foreignId('pending_registration_id')
                ->nullable()
                ->constrained('pending_registrations')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('reference_code')->unique();
            $table->string('payment_type')->index();
            $table->string('payment_method')->index();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_activities');
    }
};
