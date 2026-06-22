<?php

namespace App\Events;

use App\Models\Couple;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Шард активирован (pending → active). Диспатчится ВНУТРИ транзакции активации.
 * Слушатель из задачи #5 (геймификация) эагерно создаёт gamification_profiles
 * в той же транзакции — профиль всегда существует ровно один раз.
 */
class CoupleActivated
{
    use Dispatchable;

    public function __construct(public readonly Couple $couple) {}
}
