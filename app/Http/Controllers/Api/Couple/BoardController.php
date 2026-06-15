<?php

namespace App\Http\Controllers\Api\Couple;

use App\Data\BoardData;
use App\Services\Kanban\BoardService;

class BoardController extends CoupleController
{
    public function __construct(private readonly BoardService $boards)
    {
        parent::__construct();
    }

    /** Доска текущей пары (создаётся при первом обращении). */
    public function show(): BoardData
    {
        $board = $this->boards->ensureDefaultBoard();
        $board->load(['columns.cards']); // отсортированы по position в relations

        return BoardData::fromModel($board);
    }
}
