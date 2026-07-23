<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->string('installment_calculation_method')
                ->default('date')
                ->after('installment_enabled');

            $table->string('installment_count_mode')
                ->nullable()
                ->after('installment_calculation_method');

            $table->unsignedTinyInteger('installment_count')
                ->nullable()
                ->after('installment_count_mode');
        });

        DB::table('packages')
            ->update([
                'installment_calculation_method' => 'date',
                'installment_count_mode' => null,
                'installment_count' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn([
                'installment_calculation_method',
                'installment_count_mode',
                'installment_count',
            ]);
        });
    }
};
