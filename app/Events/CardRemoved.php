<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Карточка удалена навсегда (DELETE). Без DTO — фронту достаточно id, чтобы убрать
 * карточку из колонки/бэклога.
 */
class CardRemoved implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $coupleId,
        public readonly int $cardId,
        public readonly int $columnId,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("couple.{$this->coupleId}");
    }

    public function broadcastAs(): string
    {
        return 'CardRemoved';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        Log::debug('[Event.CardRemoved] broadcasting', [
            'couple_id' => $this->coupleId,
            'card_id' => $this->cardId,
        ]);

        return [
            'cardId' => $this->cardId,
            'columnId' => $this->columnId,
        ];
    }
}
