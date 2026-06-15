<?php

namespace App\Listeners;

use App\Events\CoupleActivated;
use App\Services\Gamification\GamificationService;

/**
 * На активацию шарда: эагерно создаём профиль и фиксируем событие активации (+стартовый XP).
 * Выполняется синхронно ВНУТРИ транзакции accept() — профиль всегда существует.
 */
class CreateGamificationProfile
{
    public function __construct(private readonly GamificationService $gamification)
    {
    }

    public function handle(CoupleActivated $event): void
    {
        $this->gamification->ensureProfile($event->couple);
        $this->gamification->recordEvent($event->couple, 'couple_activated');
    }
}
