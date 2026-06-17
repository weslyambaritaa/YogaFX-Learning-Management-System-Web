<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_tier_id')
                ->constrained('access_tiers')
                ->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone', 50);
            $table->string('country');
            $table->decimal('amount_snapshot', 10, 2);
            $table->string('status')->default('created')->index();
            $table->timestamp('checkout_opened_at')->nullable();
            $table->timestamp('payment_succeeded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
