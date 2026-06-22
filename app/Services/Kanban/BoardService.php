<?php

namespace App\Services\Kanban;

use App\Data\ColumnData;
use App\Events\ColumnBroadcast;
use App\Events\ColumnRemoved;
use App\Events\ColumnsReordered;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Support\CoupleContext;
use Illuminate\Support\Facades\Log;

/**
 * Доски и колонки. Работает в текущем CoupleContext (couple_id проставит CoupleScoped).
 */
class BoardService
{
    public function __construct(private readonly CoupleContext $context) {}

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

        broadcast(new ColumnBroadcast($board->couple_id, 'created', ColumnData::fromModel($column->load('cards'))))->toOthers();

        return $column;
    }

    public function renameColumn(BoardColumn $column, string $title): void
    {
        $column->update(['title' => $title]);
        Log::debug('[BoardService.renameColumn]', ['column_id' => $column->id]);

        broadcast(new ColumnBroadcast($column->couple_id, 'updated', ColumnData::fromModel($column->fresh()->load('cards'))))->toOthers();
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

        // Вещаем ИТОГОВЫЙ порядок (после пиннинга терминальной) — партнёр пересортирует свои колонки.
        broadcast(new ColumnsReordered($this->context->id(), array_map('intval', $orderedIds)))->toOthers();
    }

    public function removeColumn(BoardColumn $column): void
    {
        // Терминальную колонку («Готово») удалять нельзя — она выполняет роль галочки «выполнено».
        if ($column->is_terminal) {
            Log::warning('[BoardService.removeColumn] blocked terminal', ['column_id' => $column->id]);
            abort(422, 'Колонку «Готово» нельзя удалить.');
        }

        $coupleId = $column->couple_id;
        $columnId = $column->id;

        $column->delete(); // карточки удалятся каскадом (board_column_id FK CASCADE)
        Log::info('[BoardService.removeColumn]', ['column_id' => $columnId]);

        broadcast(new ColumnRemoved($coupleId, $columnId))->toOthers();
    }
}
