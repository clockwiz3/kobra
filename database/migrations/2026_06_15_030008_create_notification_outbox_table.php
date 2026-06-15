<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// OUTBOX доставки. Имя `notification_outbox` (не `notifications`) — чтобы не конфликтовать
// с relation Notifiable::notifications() из Laravel. Полиморфный notifiable (user|waitlist).
// НЕ CoupleScoped (роутинг по notifiable).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type'); // morphMap alias: user|waitlist
            $table->unsignedBigInteger('notifiable_id');
            $table->string('channel'); // email|telegram|push
            $table->string('type');    // event-key
            $table->json('payload')->nullable(); // минимальный/несенситивный
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            // только для scoped admin-reads / purge couple-originated уведомлений
            $table->foreignId('couple_id')->nullable()->constrained('couples')->nullOnDelete();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
    }
};
