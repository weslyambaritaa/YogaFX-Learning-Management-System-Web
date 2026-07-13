<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'student_tag')) {
                $table->string('student_tag', 20)
                    ->default('normal')
                    ->after('account_status');
            }
        });

        DB::table('users')
            ->where('role', 'student')
            ->whereNull('student_tag')
            ->update([
                'student_tag' => 'normal',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'student_tag')) {
                $table->dropColumn('student_tag');
            }
        });
    }
};
