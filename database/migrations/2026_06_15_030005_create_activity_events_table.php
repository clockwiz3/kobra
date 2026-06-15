<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED, append-only. Топливо геймификации (стрик/XP/ачивки). CoupleScoped ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // event-key
            $table->json('payload')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['couple_id', 'occurred_at']);   // shard-local таймлайны
            $table->index(['couple_id', 'actor_user_id']);  // выводимая атрибуция
        });

        // Идемпотентность события в рамках шарда (partial unique, Postgres).
        DB::statement('CREATE UNIQUE INDEX activity_events_couple_idem_unique ON activity_events (couple_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
