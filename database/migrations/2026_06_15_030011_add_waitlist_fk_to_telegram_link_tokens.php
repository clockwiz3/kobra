<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FK telegram_link_tokens.waitlist_entry_id → waitlist_entries (отложен из #6, таблица создана в #7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_link_tokens', function (Blueprint $table) {
            $table->foreign('waitlist_entry_id')
                ->references('id')->on('waitlist_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['waitlist_entry_id']);
        });
    }
};
