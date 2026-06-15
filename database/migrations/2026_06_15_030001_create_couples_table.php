<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SHARD ROOT: строка couples = изолированный shard (tenant). НЕ CoupleScoped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('status')->default('pending')->index(); // pending|active|archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings')->nullable(); // тема/гамма (Customization), anniversary, timezone
            $table->unsignedInteger('cache_version')->default(0); // бамп при изменении членства
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // dissolution = archive + soft delete
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couples');
    }
};
