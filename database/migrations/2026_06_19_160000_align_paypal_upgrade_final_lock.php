<?php

use App\Models\AccessTier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_tiers') && ! Schema::hasColumn('access_tiers', 'level')) {
            Schema::table('access_tiers', function (Blueprint $table): void {
                $table->unsignedInteger('level')->nullable()->after('currency_code');
            });

            $tiers = DB::table('access_tiers')
                ->select(['id', 'slug'])
                ->orderBy('price')
                ->orderBy('id')
                ->get();

            foreach ($tiers as $index => $tier) {
                $fallbackLevel = $index + 1;
                $level = match ($tier->slug) {
                    AccessTier::SLUG_STARTER_KIT => 1,
                    AccessTier::SLUG_ONLINE => 2,
                    AccessTier::SLUG_MASTER_CLASS => 3,
                    default => $fallbackLevel,
                };

                DB::table('access_tiers')
                    ->where('id', $tier->id)
                    ->update(['level' => $level]);
            }

            Schema::table('access_tiers', function (Blueprint $table): void {
                $table->unsignedInteger('level')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('payment_activities') && Schema::hasColumn('payment_activities', 'payment_reference')) {
            Schema::table('payment_activities', function (Blueprint $table): void {
                $table->string('payment_reference')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_activities') && Schema::hasColumn('payment_activities', 'payment_reference')) {
            DB::table('payment_activities')
                ->whereNull('payment_reference')
                ->update(['payment_reference' => 'ROLLBACK-PENDING']);

            Schema::table('payment_activities', function (Blueprint $table): void {
                $table->string('payment_reference')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('access_tiers') && Schema::hasColumn('access_tiers', 'level')) {
            Schema::table('access_tiers', function (Blueprint $table): void {
                $table->dropColumn('level');
            });
        }
    }
};
