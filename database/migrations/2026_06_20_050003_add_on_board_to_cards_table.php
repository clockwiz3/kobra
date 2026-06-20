<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED (cards). Карточка на доске (true) либо «убрана с доски» в бэклог (false, НЕ удаление). ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->boolean('on_board')->default(true)->after('done');
            $table->index(['board_column_id', 'on_board', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['board_column_id', 'on_board', 'position']);
            $table->dropColumn('on_board');
        });
    }
};
