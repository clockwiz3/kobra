<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

// COUPLE-OWNED. Терминальная («Готово») колонка доски: попадание карточки в неё = «выполнено».
// Ровно одна терминальная колонка на доску (partial unique index). ⚑
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_columns', function (Blueprint $table) {
            $table->boolean('is_terminal')->default(false)->after('position');
        });

        // Инвариант целостности: не больше одной терминальной колонки на доску.
        // Partial unique index поддержан и pgsql, и sqlite; предикат булева — диалект-зависим.
        $predicate = $this->terminalPredicate();
        DB::statement("CREATE UNIQUE INDEX board_columns_one_terminal_per_board ON board_columns (board_id) WHERE {$predicate}");

        $this->backfillTerminalColumns();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS board_columns_one_terminal_per_board');

        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropColumn('is_terminal');
        });
    }

    /** Булев предикат для partial index в текущем диалекте (pgsql не сравнивает boolean с int). */
    private function terminalPredicate(): string
    {
        return Schema::getConnection()->getDriverName() === 'pgsql' ? 'is_terminal = true' : 'is_terminal = 1';
    }

    /**
     * Бэкфилл по ВСЕМ парам: каждой доске назначить ровно одну терминальную колонку.
     * Правило: существующая колонка с title='Готово' (последняя по position) → терминальная;
     * если такой нет — создать «Готово» в конце с явным couple_id (= board.couple_id).
     *
     * Raw query builder (а не Eloquent) намеренно: модели couple-owned под CoupleScoped/fail-closed,
     * а миграция исполняется вне CoupleContext. Это документированное исключение слоя миграций
     * (см. ARCHITECTURE anti-patterns / rules/base.md: app-код так не делает).
     */
    private function backfillTerminalColumns(): void
    {
        $now = now();
        $created = 0;
        $flagged = 0;

        $boards = DB::table('boards')->get(['id', 'couple_id']);
        foreach ($boards as $board) {
            $existing = DB::table('board_columns')
                ->where('board_id', $board->id)
                ->where('title', 'Готово')
                ->orderByDesc('position')
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                DB::table('board_columns')->where('id', $existing->id)->update(['is_terminal' => true, 'updated_at' => $now]);
                $flagged++;

                continue;
            }

            $maxPosition = (int) DB::table('board_columns')->where('board_id', $board->id)->max('position');
            DB::table('board_columns')->insert([
                'couple_id' => $board->couple_id,
                'board_id' => $board->id,
                'title' => 'Готово',
                'position' => $maxPosition + 1,
                'is_terminal' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created++;
        }

        Log::info('[Migration.add_is_terminal] backfilled', [
            'boards' => $boards->count(),
            'flagged' => $flagged,
            'created' => $created,
        ]);
    }
};
