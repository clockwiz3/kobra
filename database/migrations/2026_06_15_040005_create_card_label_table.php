<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED pivot карточка↔метка. CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['card_id', 'label_id']);
            $table->index('couple_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_label');
    }
};
