<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pivot членства пары. НЕ CoupleScoped (доступ через lifecycle-сервисы).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couple_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('partner'); // owner|partner
            $table->boolean('is_active')->default(false); // зеркалит couples.status
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'user_id']);
            $table->index('user_id'); // обратный tenant-резолв O(1)
        });

        // DB-инвариант: одна АКТИВНАЯ пара на юзера (partial unique, Postgres).
        DB::statement('CREATE UNIQUE INDEX couple_user_one_active_per_user ON couple_user (user_id) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('couple_user');
    }
};
