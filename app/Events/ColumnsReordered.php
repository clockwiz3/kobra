<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Порядок колонок изменён (drag&drop). orderedIds — новый порядок id колонок;
 * фронт пересортировывает свой массив колонок по этому порядку.
 */
class ColumnsReordered implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param int[] $orderedIds */
    public function __construct(
        public readonly int $coupleId,
        public readonly array $orderedIds,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("couple.{$this->coupleId}");
    }

    public function broadcastAs(): string
    {
        return 'ColumnsReordered';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        Log::debug('[Event.ColumnsReordered] broadcasting', [
            'couple_id' => $this->coupleId,
            'count' => count($this->orderedIds),
        ]);

        return [
            'orderedIds' => array_values($this->orderedIds),
        ];
    }
}
