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
        if (Schema::hasTable('access_tiers')) {
            Schema::table('access_tiers', function (Blueprint $table) {
                if (! Schema::hasColumn('access_tiers', 'slug')) {
                    $table->string('slug')->unique()->after('name');
                }

                if (! Schema::hasColumn('access_tiers', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index()->after('description');
                }
            });

            return;
        }

        Schema::create('access_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_tiers');
    }
};
