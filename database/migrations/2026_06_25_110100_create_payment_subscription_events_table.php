<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_subscription_id')
                ->nullable()
                ->constrained('payment_subscriptions')
                ->nullOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();
            $table->foreignId('payment_activity_id')
                ->nullable()
                ->constrained('payment_activities')
                ->nullOnDelete();
            $table->string('provider');
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
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_subscription_events');
    }
};
