<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Инвайт партнёра. НЕ CoupleScoped (pre-active shard).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couple_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('invite_code')->unique(); // deep-link
            $table->string('email')->nullable();
            $table->string('status')->default('pending'); // pending|accepted|expired
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couple_invitations');
    }
};
