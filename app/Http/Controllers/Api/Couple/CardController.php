<?php

namespace App\Http\Controllers\Api\Couple;

use App\Data\CardData;
use App\Enums\CardAssignee;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Services\Kanban\CardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

// id-параметры + findOrFail в контроллере (couple-scoped): model-binding нельзя — оно
// срабатывает в SubstituteBindings ДО middleware couple.context.
class CardController extends CoupleController
{
    public function __construct(private readonly CardService $cards)
    {
        parent::__construct();
    }

    public function store(StoreCardRequest $request): CardData
    {
        $data = $request->validated();
        $column = BoardColumn::findOrFail($data['column_id']); // scoped
        // Опциональные параметры карточки прокидываем в сервис attrs-массивом (масштабирование).
        $attrs = array_intersect_key($data, array_flip(['description', 'due_at', 'due_end_at']));
        Log::debug('[CardController.store]', ['column_id' => $data['column_id'], 'keys' => array_keys($data)]);

        $card = $this->cards->create(
            $column,
            $data['title'],
            CardAssignee::from($data['assignee'] ?? 'together'),
            $attrs,
            $request->user(),
        );

        return CardData::fromModel($card);
    }

    public function update(UpdateCardRequest $request, int $cardId): CardData
    {
        $data = $request->validated();
        if (array_key_exists('assignee', $data)) {
            $data['assignee'] = CardAssignee::from($data['assignee']);
        }
        Log::debug('[CardController.update]', ['card_id' => $cardId, 'keys' => array_keys($data)]);

        return CardData::fromModel($this->cards->update(Card::findOrFail($cardId), $data));
    }

    public function move(Request $request, int $cardId): CardData
    {
        $data = $request->validate([
            'column_id' => 'required|integer',
            'position' => 'required|integer|min:0',
        ]);
        // Целевую колонку резолвим через CoupleScoped (findOrFail → 404 на чужую колонку):
        // иначе move в чужой board_column_id «уносит» карточку с доски своей пары (data-integrity).
        $column = BoardColumn::findOrFail($data['column_id']); // scoped
        Log::debug('[CardController.move]', ['card_id' => $cardId, 'to_column' => $column->id, 'position' => $data['position']]);

        // Готовность карточки выводится из попадания в терминальную колонку — актор нужен для геймификации.
        return CardData::fromModel($this->cards->move(Card::findOrFail($cardId), $column, $data['position'], $request->user()));
    }

    public function destroy(int $cardId): Response
    {
        Card::findOrFail($cardId)->delete();

        return response()->noContent();
    }
}
