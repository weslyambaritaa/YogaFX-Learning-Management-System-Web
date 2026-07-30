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
        Schema::create('accommodation_payment_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_payment_subscription_id')
                ->nullable()
                ->constrained('accommodation_payment_subscriptions')
                ->nullOnDelete();
            $table->string('provider')->default('paypal');
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('provider_event_type');
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_order_id')->nullable();
            $table->string('provider_capture_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('received')->index();
            $table->json('payload');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_subscription_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodation_payment_subscription_events');
    }
};
