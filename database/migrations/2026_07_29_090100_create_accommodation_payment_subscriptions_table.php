<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accommodation_payment_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_booking_id')
                ->constrained('accommodation_bookings')
                ->cascadeOnDelete();
            $table->string('provider')->default('paypal');
            $table->string('provider_product_id')->nullable();
            $table->string('provider_plan_id')->nullable();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('installment_count');
            $table->unsignedInteger('installments_paid_count')->default(0);
            $table->string('currency_code', 3);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('monthly_amount', 12, 2);
            $table->decimal('first_payment_amount', 12, 2);
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('first_payment_paid_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('final_due_at')->nullable();
            $table->timestamp('grace_deadline_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_payment_failed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['accommodation_booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodation_payment_subscriptions');
    }
};
