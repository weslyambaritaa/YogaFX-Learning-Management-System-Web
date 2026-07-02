<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_brandings', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key')->unique();
            $table->string('logo_path')->nullable();
            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_brandings');
    }
};
