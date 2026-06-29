<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_tier_id')
                ->nullable()
                ->constrained('access_tiers')
                ->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('currency_code', 3);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('installment_enabled')->default(false);
            $table->string('billing_interval_unit')->nullable();
            $table->unsignedInteger('billing_interval_count')->nullable();
            $table->unsignedTinyInteger('fixed_billing_day')->nullable();
            $table->unsignedTinyInteger('installment_deadline_month')->nullable();
            $table->unsignedTinyInteger('installment_deadline_day')->nullable();
            $table->string('paypal_product_id')->nullable();
            $table->string('paypal_plan_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['access_tier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
