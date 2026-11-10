<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Линковка Telegram (deep-link). Cross/pre-shard. НЕ CoupleScoped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique(); // t.me/<bot>?start=<token>
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->string('status')->default('pending'); // pending|linked|expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
    }
};
