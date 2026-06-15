<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED. Анлок ачивки парой (идемпотентно). CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievement_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->foreignId('unlocked_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // warmth-атрибуция
            $table->timestamp('unlocked_at')->useCurrent();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'achievement_id']); // идемпотентно per shard
            $table->index('couple_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_unlocks');
    }
};
