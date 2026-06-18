<?php

namespace App\Services\Kanban;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Support\Facades\Log;

/**
 * Доски и колонки. Работает в текущем CoupleContext (couple_id проставит CoupleScoped).
 */
class BoardService
{
    /** Дефолтная доска пары + стартовые колонки (идемпотентно). */
    public function ensureDefaultBoard(): Board
    {
        $board = Board::first();
        if ($board !== null) {
            return $board;
        }

        $board = Board::create(['name' => 'Наша доска', 'position' => 0]);
        $terminalId = null;
        foreach (['Идеи', 'На неделе', 'Свидания', 'Готово'] as $i => $title) {
            $isTerminal = $title === 'Готово'; // «Готово» — терминальная колонка (роль галочки «выполнено»)
            $column = BoardColumn::create(['board_id' => $board->id, 'title' => $title, 'position' => $i, 'is_terminal' => $isTerminal]);
            if ($isTerminal) {
                $terminalId = $column->id;
            }
        }
        Log::info('[BoardService.ensureDefaultBoard] created', ['board_id' => $board->id, 'terminal_column_id' => $terminalId]);

        return $board;
    }

    /**
     * Добавить колонку. Новая колонка встаёт ПЕРЕД терминальной («Готово»), а терминальная
     * сдвигается на одну позицию вниз — инвариант «Готово всегда последняя» сохраняется.
     */
    public function addColumn(Board $board, string $title): BoardColumn
    {
        $terminal = $board->columns()->where('is_terminal', true)->first();
        if ($terminal !== null) {
            $position = $terminal->position;
            $terminal->update(['position' => $position + 1]); // «Готово» снова последняя
        } else {
            $position = $board->columns()->count();
        }

        $column = BoardColumn::create([
            'board_id' => $board->id,
            'title' => $title,
            'position' => $position,
            'is_terminal' => false,
        ]);
        Log::info('[BoardService.addColumn]', ['board_id' => $board->id, 'column_id' => $column->id, 'position' => $position]);

        return $column;
    }

    public function renameColumn(BoardColumn $column, string $title): void
    {
        $column->update(['title' => $title]);
        Log::debug('[BoardService.renameColumn]', ['column_id' => $column->id]);
    }

    /**
     * Переупорядочить колонки. Бэкстоп инварианта «Готово всегда последняя»: терминальную
     * колонку принудительно выносим в конец независимо от присланного фронтом порядка.
     *
     * @param  array<int,int>  $orderedIds
     */
    public function reorderColumns(array $orderedIds): void
    {
        $terminalId = BoardColumn::where('is_terminal', true)->value('id');
        if ($terminalId !== null) {
            $orderedIds = array_values(array_filter($orderedIds, fn ($id) => (int) $id !== (int) $terminalId));
            $orderedIds[] = (int) $terminalId; // терминальная — всегда последняя
        }

        foreach ($orderedIds as $i => $id) {
            BoardColumn::whereKey($id)->update(['position' => $i]); // scoped: только колонки текущей пары
        }
        Log::debug('[BoardService.reorderColumns]', ['count' => count($orderedIds), 'terminal_pinned' => $terminalId !== null]);
    }

    public function removeColumn(BoardColumn $column): void
    {
        // Терминальную колонку («Готово») удалять нельзя — она выполняет роль галочки «выполнено».
        if ($column->is_terminal) {
            Log::warning('[BoardService.removeColumn] blocked terminal', ['column_id' => $column->id]);
            abort(422, 'Колонку «Готово» нельзя удалить.');
        }

        $column->delete(); // карточки удалятся каскадом (board_column_id FK CASCADE)
        Log::info('[BoardService.removeColumn]', ['column_id' => $column->id]);
    }
}
