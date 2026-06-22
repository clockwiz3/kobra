<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Партнёр обновил «внешний вид» (рамку карточек) в профиле → доска второго партнёра
 * перекрашивается по слоту (him/her) без полного fetchBoard. Без PII: только slot и
 * appearance-конфиг.
 */
class MemberAppearanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param array<string, mixed>|null $appearance */
    public function __construct(
        public readonly int $coupleId,
        public readonly string $slot,
        public readonly ?array $appearance,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("couple.{$this->coupleId}");
    }

    public function broadcastAs(): string
    {
        return 'MemberAppearanceUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        Log::debug('[Event.MemberAppearanceUpdated] broadcasting', [
            'couple_id' => $this->coupleId,
            'slot' => $this->slot,
        ]);

        return [
            'slot' => $this->slot,
            'appearance' => $this->appearance,
        ];
    }
}
