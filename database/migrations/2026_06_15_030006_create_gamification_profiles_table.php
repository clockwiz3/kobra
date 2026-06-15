<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED, ОДИН профиль на shard (UNIQUE couple_id). Общая прогрессия пары. CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->unique()->constrained('couples')->cascadeOnDelete();
            $table->bigInteger('xp')->default(0);
            $table->smallInteger('level')->default(1);
            $table->integer('hearts_balance')->default(0); // валюта (магазин — будущее)
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('streak_last_qualified_on')->nullable();
            $table->unsignedBigInteger('last_processed_event_id')->nullable(); // exactly-once XP
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_profiles');
    }
};
