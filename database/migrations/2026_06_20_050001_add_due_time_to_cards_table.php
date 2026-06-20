<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED (cards). Время суток дедлайна/события: 'HH:MM' (H:i, локальное, без таймзоны/даты). ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('due_time', 5)->nullable()->after('due_end_at'); // 'HH:MM'
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('due_time');
        });
    }
};
