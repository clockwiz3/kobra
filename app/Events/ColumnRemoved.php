<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Колонка удалена. Фронт убирает колонку (и её карточки) из стора.
 */
class ColumnRemoved implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $coupleId,
        public readonly int $columnId,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("couple.{$this->coupleId}");
    }

    public function broadcastAs(): string
    {
        return 'ColumnRemoved';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        Log::debug('[Event.ColumnRemoved] broadcasting', [
            'couple_id' => $this->coupleId,
            'column_id' => $this->columnId,
        ]);

        return [
            'columnId' => $this->columnId,
        ];
    }
}
