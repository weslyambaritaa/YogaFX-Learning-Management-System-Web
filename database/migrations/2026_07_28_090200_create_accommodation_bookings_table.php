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
        Schema::create('accommodation_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique();
            $table->foreignId('accommodation_id')->constrained('accommodations')->restrictOnDelete();
            $table->foreignId('accommodation_room_type_id')->constrained('accommodation_room_types')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('nights');
            $table->decimal('price_per_night', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency_code', 3);
            $table->string('status')->default('pending_payment')->index();
            $table->string('paypal_order_id')->nullable()->index();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(
                ['accommodation_room_type_id', 'status', 'check_in_date', 'check_out_date'],
                'accommodation_bookings_availability_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodation_bookings');
    }
};
