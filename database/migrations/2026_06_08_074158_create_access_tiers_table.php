<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kept as a no-op to preserve migration history.
        // The canonical access tier schema is defined in
        // 2026_06_09_090000_create_access_tiers_table.php.
    }

    public function down(): void
    {
        //
    }
};
