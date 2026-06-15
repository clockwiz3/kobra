<?php

namespace App\Services\Couples;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Уничтожение аккаунта (right-to-be-forgotten). Это единственный путь, который
 * РЕАЛЬНО удаляет couple-данные (dissolution лишь архивирует).
 *
 * `purge()` — деструктивный примитив. Рекомендуемая обёртка (Q3): grace-window +
 * уведомление партнёра ПЕРЕД purge — добавляется отдельной задачей (planner/#11),
 * т.к. удаление уничтожает ОБЩИЕ данные второго партнёра без его согласия.
 */
class CoupleDeletionService
{
    public function purge(User $user): void
    {
        DB::transaction(function () use ($user) {
            $couples = $user->coupleMemberships()->get();

            foreach ($couples as $couple) {
                Log::info('[CoupleDeletionService.purge] force-delete shard (CASCADE)', [
                    'couple_id' => $couple->id,
                ]);
                // forceDelete → реальный DELETE → FK ON DELETE CASCADE по couple-owned детям.
                $couple->forceDelete();
            }

            $user->forceDelete();

            Log::info('[CoupleDeletionService.purge] user purged', [
                'user_id' => $user->id,
                'couples' => $couples->pluck('id')->all(),
            ]);
        });

        // TODO(#11): уведомить выжившего партнёра (CoupleDestroyed) до/после purge per Q3-политике.
    }
}
