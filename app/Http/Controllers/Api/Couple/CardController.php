<?php

namespace App\Http\Controllers\Api\Couple;

use App\Data\CardData;
use App\Enums\CardAssignee;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Services\Kanban\CardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// id-параметры + findOrFail в контроллере (couple-scoped): model-binding нельзя — оно
// срабатывает в SubstituteBindings ДО middleware couple.context.
class CardController extends CoupleController
{
    public function __construct(private readonly CardService $cards)
    {
        parent::__construct();
    }

    public function store(Request $request): CardData
    {
        $data = $request->validate([
            'column_id' => 'required|integer',
            'title' => 'required|string|max:160',
            'assignee' => 'nullable|in:him,her,together',
        ]);
        $column = BoardColumn::findOrFail($data['column_id']); // scoped
        $card = $this->cards->create($column, $data['title'], CardAssignee::from($data['assignee'] ?? 'together'));

        return CardData::fromModel($card);
    }

    public function update(Request $request, int $cardId): CardData
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:160',
            'description' => 'sometimes|nullable|string',
            'assignee' => 'sometimes|in:him,her,together',
        ]);
        if (array_key_exists('assignee', $data)) {
            $data['assignee'] = CardAssignee::from($data['assignee']);
        }

        return CardData::fromModel($this->cards->update(Card::findOrFail($cardId), $data));
    }

    public function move(Request $request, int $cardId): CardData
    {
        $data = $request->validate([
            'column_id' => 'required|integer',
            'position' => 'required|integer|min:0',
        ]);

        return CardData::fromModel($this->cards->move(Card::findOrFail($cardId), $data['column_id'], $data['position']));
    }

    public function toggleDone(Request $request, int $cardId): CardData
    {
        return CardData::fromModel($this->cards->toggleDone(Card::findOrFail($cardId), $request->user()));
    }

    public function destroy(int $cardId): Response
    {
        Card::findOrFail($cardId)->delete();

        return response()->noContent();
    }
}
