<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tshirt_size', 3)->nullable();
            $table->text('favorite_song')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_whatsapp', 50)->nullable();

            $table->boolean('has_medical_issues')->nullable();
            $table->text('medical_issues_details')->nullable();

            $table->boolean('is_taking_medication')->nullable();
            $table->text('medication_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tshirt_size',
                'favorite_song',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_whatsapp',
                'has_medical_issues',
                'medical_issues_details',
                'is_taking_medication',
                'medication_details',
            ]);
        });
    }
};