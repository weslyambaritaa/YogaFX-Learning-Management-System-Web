<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_brandings', function (Blueprint $table) {
            $table->text('logo_html')->nullable()->after('logo_path');
        });

        DB::table('email_brandings')
            ->whereNull('logo_html')
            ->update([
                'logo_html' => '<p style="margin: 0; font-size: 18px; font-weight: 600; color: #0f172a;">'.e((string) config('app.name', 'YogaFX LMS')).'</p>',
            ]);
    }

    public function down(): void
    {
        Schema::table('email_brandings', function (Blueprint $table) {
            $table->dropColumn('logo_html');
        });
    }
};
