<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED. Колонка доски. CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('title');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['couple_id', 'position']);
            $table->index(['board_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
    }
};
