<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PRE-ACCOUNT данные. НЕ CoupleScoped. БЕЗ рефералов/лидерборда/позиции.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('status')->default('pending'); // pending|confirmed|invited
            $table->string('confirmation_token')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'id']); // под производный статичный счётчик позиции
        });

        // confirmation_token уникален, но допускает множественные NULL (Postgres partial unique).
        DB::statement('CREATE UNIQUE INDEX waitlist_entries_confirmation_token_unique ON waitlist_entries (confirmation_token) WHERE confirmation_token IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
