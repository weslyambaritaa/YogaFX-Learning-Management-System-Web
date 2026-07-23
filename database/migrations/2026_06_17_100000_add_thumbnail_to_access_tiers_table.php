<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_tiers', function (Blueprint $table) {
            if (! Schema::hasColumn('access_tiers', 'thumbnail')) {
                $table->string('thumbnail')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('access_tiers', function (Blueprint $table) {
            if (Schema::hasColumn('access_tiers', 'thumbnail')) {
                $table->dropColumn('thumbnail');
            }
        });
    }
};
