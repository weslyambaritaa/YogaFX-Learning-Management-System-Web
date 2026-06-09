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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('whatsapp')->nullable()->after('email');
            $table->string('preferred_certificate_picture')->nullable()->after('whatsapp');
            $table->string('instagram')->nullable()->after('preferred_certificate_picture');
            $table->string('country')->nullable()->after('instagram');
            $table->date('birth_date')->nullable()->after('country');
            $table->string('gender')->nullable()->after('birth_date');
            $table->string('practicing_yoga_for')->nullable()->after('gender');
            $table->string('yoga_sequence_experience')->nullable()->after('practicing_yoga_for');
            $table->unsignedSmallInteger('hours_per_week')->nullable()->after('yoga_sequence_experience');
            $table->string('current_fitness_level')->nullable()->after('hours_per_week');
            $table->string('flexibility_rating')->nullable()->after('current_fitness_level');
            $table->text('motivation')->nullable()->after('flexibility_rating');
            $table->text('why_yogafx')->nullable()->after('motivation');
            $table->text('how_did_you_find_us')->nullable()->after('why_yogafx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'whatsapp',
                'preferred_certificate_picture',
                'instagram',
                'country',
                'birth_date',
                'gender',
                'practicing_yoga_for',
                'yoga_sequence_experience',
                'hours_per_week',
                'current_fitness_level',
                'flexibility_rating',
                'motivation',
                'why_yogafx',
                'how_did_you_find_us',
            ]);
        });
    }
};
