<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GLOBAL каталог ачивок. НЕ CoupleScoped (общие определения, read-only для tenant'ов).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_secret')->default(false); // пасхалки
            $table->integer('points')->default(0);
            $table->json('criteria')->nullable(); // условия анлока
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
