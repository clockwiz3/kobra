<?php

namespace App\Services\Kanban;

use App\Enums\CardAssignee;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Couple;
use App\Models\User;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Log;

/**
 * Карточки. Работает в текущем CoupleContext. Завершение карточки → событие геймификации.
 */
class CardService
{
    public function __construct(private readonly GamificationService $gamification)
    {
    }

    public function create(BoardColumn $column, string $title, CardAssignee $assignee = CardAssignee::Together): Card
    {
        $card = Card::create([
            'board_column_id' => $column->id,
            'title' => $title,
            'assignee' => $assignee,
            'position' => $column->cards()->count(),
        ]);
        Log::info('[CardService.create]', ['card_id' => $card->id, 'column_id' => $column->id]);

        return $card;
    }

    /** @param array<string,mixed> $attrs */
    public function update(Card $card, array $attrs): Card
    {
        $card->update($attrs);
        Log::debug('[CardService.update]', ['card_id' => $card->id]);

        return $card;
    }

    public function move(Card $card, int $toColumnId, int $toPosition): Card
    {
        $card->update(['board_column_id' => $toColumnId, 'position' => $toPosition]);
        Log::info('[CardService.move]', ['card_id' => $card->id, 'to_column' => $toColumnId, 'to_pos' => $toPosition]);

        return $card;
    }

    /**
     * Переключить готовность. На done=true — событие 'task_completed' (XP/стрик/ачивка),
     * идемпотентно по карточке (анти-фарм: повторное вкл/выкл не начисляет заново).
     * Снятие done — без отрицательных начислений (upside-only).
     */
    public function toggleDone(Card $card, User $actor): Card
    {
        $newDone = ! $card->done;
        $card->update(['done' => $newDone, 'completed_at' => $newDone ? now() : null]);

        if ($newDone) {
            $couple = Couple::find($card->couple_id);
            if ($couple !== null) {
                $this->gamification->recordEvent(
                    $couple,
                    'task_completed',
                    $actor,
                    ['card_id' => $card->id],
                    'card_done_'.$card->id, // idempotency key — одна награда за карточку
                );
            }
        }
        Log::info('[CardService.toggleDone]', ['card_id' => $card->id, 'done' => $newDone, 'couple_id' => $card->couple_id]);

        return $card;
    }
}
