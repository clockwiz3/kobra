<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED (cards). Конец диапазона дедлайна: due_at=начало/одиночная дата, due_end_at=конец (null для single). ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->timestamp('due_end_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('due_end_at');
        });
    }
};
