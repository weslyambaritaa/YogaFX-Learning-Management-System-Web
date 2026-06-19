<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_password_change_requests', function (Blueprint $table) {
            $table->string('origin', 32)
                ->default('web')
                ->after('otp_hash');
        });
    }

    public function down(): void
    {
        Schema::table('student_password_change_requests', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
