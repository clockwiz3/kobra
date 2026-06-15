<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED. Доска пары. CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->string('name')->default('Наша доска');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['couple_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
