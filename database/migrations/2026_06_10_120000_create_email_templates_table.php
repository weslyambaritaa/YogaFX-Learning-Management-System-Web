<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('notification_type')->unique();
            $table->string('notification_name');
            $table->boolean('is_enabled')->default(false);
            $table->text('admin_recipients')->nullable();
            $table->string('subject_user')->nullable();
            $table->text('body_user')->nullable();
            $table->string('subject_admin')->nullable();
            $table->text('body_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
