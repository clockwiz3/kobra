<?php

namespace App\Events;

use App\Data\CardData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Карточка изменилась у одного партнёра → второй видит сразу. action различает
 * семантику применения на фронте; полный CardData позволяет применить любую из них
 * (created → вставка, updated → патч, moved → перенос+done, returned → из бэклога).
 * ShouldBroadcastNow: dev-очередь sync → вещаем синхронно и предсказуемо.
 */
class CardBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param 'created'|'backlogCreated'|'updated'|'moved'|'returned' $action */
    public function __construct(
        public readonly int $coupleId,
        public readonly string $action,
        public readonly CardData $card,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("couple.{$this->coupleId}");
    }

    public function broadcastAs(): string
    {
        return 'CardBroadcast';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        Log::debug('[Event.CardBroadcast] broadcasting', [
            'couple_id' => $this->coupleId,
            'action' => $this->action,
            'card_id' => $this->card->id,
        ]);

        return [
            'action' => $this->action,
            'card' => $this->card->toArray(),
        ];
    }
}
