<?php

use App\Enums\CoupleRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| presence-канал пары — единственная точка real-time для shard'а: на нём
| вещаются доменные события (карточки/колонки/профиль) И ведётся онлайн-
| присутствие партнёра. Авторизация fail-closed по членству в паре —
| это security-инвариант изоляции shard (как CoupleScope): попасть на
| чужой couple.{id} нельзя.
|
*/

Broadcast::channel('couple.{coupleId}', function (User $user, int $coupleId) {
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

    // Возврат массива → presence-членство: эти поля видны остальным подписчикам
    // (Echo .here()/.joining()/.leaving()) для онлайн-статуса и атрибуции.
    return [
        'id' => $user->id,
        'slot' => $slot,
        'name' => $user->name,
    ];
});
