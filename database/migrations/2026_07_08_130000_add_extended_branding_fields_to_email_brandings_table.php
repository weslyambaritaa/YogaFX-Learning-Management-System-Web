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
            $table->text('email_header_html')->nullable()->after('logo_html');
            $table->text('email_signature_html')->nullable()->after('email_header_html');
            $table->text('pdf_header_html')->nullable()->after('email_signature_html');
            $table->text('watermark_html')->nullable()->after('pdf_header_html');
        });

        DB::table('email_brandings')->update([
            'email_header_html' => DB::raw('COALESCE(email_header_html, header_html)'),
            'email_signature_html' => DB::raw('COALESCE(email_signature_html, footer_html)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('email_brandings', function (Blueprint $table) {
            $table->dropColumn([
                'email_header_html',
                'email_signature_html',
                'pdf_header_html',
                'watermark_html',
            ]);
        });
    }
};
