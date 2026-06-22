<?php

namespace App\Events;

use App\Data\ColumnData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Колонка создана или переименована. Полный ColumnData (created → push новой колонки,
 * updated → патч заголовка/полей на месте).
 */
class ColumnBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param 'created'|'updated' $action */
    public function __construct(
        public readonly int $coupleId,
        public readonly string $action,
        public readonly ColumnData $column,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("couple.{$this->coupleId}");
    }

    public function broadcastAs(): string
    {
        return 'ColumnBroadcast';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        Log::debug('[Event.ColumnBroadcast] broadcasting', [
            'couple_id' => $this->coupleId,
            'action' => $this->action,
            'column_id' => $this->column->id,
        ]);

        return [
            'action' => $this->action,
            'column' => $this->column->toArray(),
        ];
    }
}
