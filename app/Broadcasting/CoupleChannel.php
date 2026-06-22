<?php

namespace App\Broadcasting;

use App\Enums\CoupleRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Авторизация presence-канала пары couple.{coupleId}. Вынесено из routes/channels.php
 * в класс — тестируемо напрямую (leak-харнесс двух пар) и тонкие роуты.
 *
 * fail-closed по членству в паре (couple_user.is_active) — security-инвариант изоляции
 * shard, как CoupleScope: на чужой couple.{id} попасть нельзя.
 */
class CoupleChannel
{
    /**
     * @return array{id:int,slot:string,name:?string}|false массив member-данных для presence
     *                                                       (видны остальным подписчикам), либо false
     */
    public function join(User $user, int $coupleId): array|false
    {
        // couple_user НЕ в closed-list couple-owned таблиц — прямой запрос допустим
        // (тот же паттерн, что в ResolveCoupleContext).
        $role = DB::table('couple_user')
            ->where('user_id', $user->id)
            ->where('couple_id', $coupleId)
            ->where('is_active', true)
            ->value('role');

        if ($role === null) {
            // Попытка присоединиться к чужому/неактивному shard'у — аномалия изоляции.
            Log::warning('[channels.couple] denied', [
                'user_id' => $user->id,
                'couple_id' => $coupleId,
            ]);

            return false;
        }

        // owner → her, partner → him (стабильно, как в BoardMemberData).
        $slot = $role === CoupleRole::Owner->value ? 'her' : 'him';

        Log::debug('[channels.couple] authorized', [
            'user_id' => $user->id,
            'couple_id' => $coupleId,
            'slot' => $slot,
        ]);

        return [
            'id' => $user->id,
            'slot' => $slot,
            'name' => $user->name,
        ];
    }
}
