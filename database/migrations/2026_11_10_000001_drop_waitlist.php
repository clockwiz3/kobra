<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Вайтлист выпилен. На свежей базе этих таблиц/колонок уже нет (create-миграции удалены),
// на проде — чистим то, что осталось от старых миграций.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('telegram_link_tokens', 'waitlist_entry_id')) {
            DB::statement('ALTER TABLE telegram_link_tokens DROP CONSTRAINT IF EXISTS telegram_link_tokens_waitlist_entry_id_foreign');
            Schema::table('telegram_link_tokens', function (Blueprint $table) {
                $table->dropColumn('waitlist_entry_id');
            });
        }

        DB::table('notification_outbox')->where('notifiable_type', 'waitlist')->delete();

        Schema::dropIfExists('waitlist_entries');
    }

    public function down(): void
    {
        // Без отката: вайтлист не возвращаем.
    }
};
