<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pending_registration_id')
                ->constrained('pending_registrations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status')->default('awaiting_enrollment')->index();
            $table->timestamp('continuation_sent_at')->nullable();
            $table->timestamp('enrollment_completed_at')->nullable();
            $table->timestamp('signup_completed_at')->nullable();
            $table->timestamps();

            $table->unique('pending_registration_id');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_states');
    }
};
