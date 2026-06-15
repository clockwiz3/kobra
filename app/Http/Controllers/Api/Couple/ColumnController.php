<?php

namespace App\Http\Controllers\Api\Couple;

use App\Data\ColumnData;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Services\Kanban\BoardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// id-параметры + findOrFail в контроллере (couple-scoped): model-binding нельзя — оно
// срабатывает в SubstituteBindings ДО middleware couple.context.
class ColumnController extends CoupleController
{
    public function __construct(private readonly BoardService $boards)
    {
        parent::__construct();
    }

    public function store(Request $request): ColumnData
    {
        $data = $request->validate([
            'board_id' => 'required|integer',
            'title' => 'required|string|max:80',
        ]);
        $board = Board::findOrFail($data['board_id']); // scoped: чужая доска → 404
        $column = $this->boards->addColumn($board, $data['title']);

        return ColumnData::fromModel($column->load('cards'));
    }

    public function update(Request $request, int $columnId): ColumnData
    {
        $data = $request->validate(['title' => 'required|string|max:80']);
        $column = BoardColumn::findOrFail($columnId); // scoped
        $this->boards->renameColumn($column, $data['title']);

        return ColumnData::fromModel($column->fresh()->load('cards'));
    }

    public function reorder(Request $request): Response
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $this->boards->reorderColumns($data['ids']);

        return response()->noContent();
    }

    public function destroy(int $columnId): Response
    {
        $this->boards->removeColumn(BoardColumn::findOrFail($columnId));

        return response()->noContent();
    }
}
