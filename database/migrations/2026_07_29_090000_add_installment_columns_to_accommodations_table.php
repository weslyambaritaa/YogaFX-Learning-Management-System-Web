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
        Schema::table('accommodations', function (Blueprint $table) {
            $table->boolean('installment_enabled')->default(false)->after('is_active');
            $table->string('installment_count_mode')->nullable()->after('installment_enabled');
            $table->unsignedInteger('installment_fixed_count')->nullable()->after('installment_count_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn([
                'installment_enabled',
                'installment_count_mode',
                'installment_fixed_count',
            ]);
        });
    }
};
