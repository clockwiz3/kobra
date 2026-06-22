<?php

namespace App\Http\Controllers\Api\Couple;

use App\Data\CardData;
use App\Enums\CardAssignee;
use App\Http\Requests\StoreBacklogCardRequest;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Services\Kanban\BoardService;
use App\Services\Kanban\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Бэклог пары — карточки, убранные с доски ИЛИ созданные сразу здесь (on_board=false).
 * НЕ удаление: остаются в БД. Изоляция couple_id — глобальным CoupleScope (fail-closed).
 */
class BacklogController extends CoupleController
{
    public function __construct(
        private readonly BoardService $boards,
        private readonly CardService $cards,
    ) {
        parent::__construct();
    }

    /** Список карточек бэклога текущей пары (свежие — сверху). */
    public function index(): JsonResponse
    {
        $cards = Card::query()
            ->where('on_board', false) // scoped: только карточки текущей пары
            ->orderByDesc('updated_at')
            ->get();

        Log::debug('[BacklogController.index]', ['count' => $cards->count()]);

        // fromModel — кастомный маппинг (board_column_id→columnId, enum→value); Data::from() его не повторит,
        // поэтому маппим вручную. Массив CardData (JsonSerializable) сериализуется в чистый JSON-массив.
        return response()->json($cards->map(fn (Card $c) => CardData::fromModel($c))->all());
    }

    /** Создать карточку сразу в бэклоге (on_board=false) — не появляется на доске. */
    public function store(StoreBacklogCardRequest $request): JsonResponse
    {
        $data = $request->validated();
        $column = $this->homeColumn();
        $attrs = array_intersect_key($data, array_flip(['description', 'due_at', 'due_end_at', 'due_time', 'priority']));
        Log::debug('[BacklogController.store]', ['home_column' => $column->id, 'keys' => array_keys($data)]);

        $card = $this->cards->createInBacklog(
            $column,
            $data['title'],
            CardAssignee::from($data['assignee'] ?? 'together'),
            $attrs,
        );

        return response()->json(CardData::fromModel($card), 201);
    }

    /** Колонка-дом для новой backlog-карточки: первая не-терминальная (fallback — первая колонка). */
    private function homeColumn(): BoardColumn
    {
        $board = $this->boards->ensureDefaultBoard();

        return $board->columns()->where('is_terminal', false)->orderBy('position')->first()
            ?? $board->columns()->orderBy('position')->firstOrFail();
    }
}
