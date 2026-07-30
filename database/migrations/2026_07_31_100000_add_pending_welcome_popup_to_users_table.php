<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pending_welcome_popup')) {
                $table->boolean('pending_welcome_popup')
                    ->default(false)
                    ->after('student_tag');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pending_welcome_popup')) {
                $table->dropColumn('pending_welcome_popup');
            }
        });
    }
};
