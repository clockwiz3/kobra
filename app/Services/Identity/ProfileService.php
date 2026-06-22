<?php

namespace App\Services\Identity;

use App\Enums\CoupleRole;
use App\Events\MemberAppearanceUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Модуль Identity: редактирование собственного профиля (имя + appearance).
 * appearance.card заменяется ЦЕЛИКОМ (не recursive-merge) — иначе при переключении
 * solid↔gradient остаются протухшие ключи paint. Остальные ключи settings (theme/accent/…)
 * сохраняются.
 */
class ProfileService
{
    /**
     * Частичный апдейт профиля. $data — уже валидированные поля (name?, appearance?).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        Log::debug('[ProfileService.updateProfile] start', ['user_id' => $user->id, 'keys' => array_keys($data)]);

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        $appearanceChanged = isset($data['appearance']['card']);
        if ($appearanceChanged) {
            $settings = $user->settings ?? [];
            $settings['appearance']['card'] = $data['appearance']['card']; // атомарная замена card
            $user->settings = $settings;
        }

        $user->save();
        Log::info('[ProfileService.updateProfile] saved', ['user_id' => $user->id, 'keys' => array_keys($data)]);

        if ($appearanceChanged) {
            $this->broadcastAppearance($user);
        }

        return $user;
    }

    /**
     * Вещание новой рамки партнёру: ProfileService вне couple.context, поэтому couple_id и
     * роль резолвим явным запросом couple_user (тот же паттерн, что в ResolveCoupleContext).
     * Доска партнёра перекрасится по слоту без полного fetchBoard.
     */
    private function broadcastAppearance(User $user): void
    {
        $membership = DB::table('couple_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first(['couple_id', 'role']);

        if ($membership === null) {
            return; // юзер не в активной паре — некому вещать
        }

        $slot = $membership->role === CoupleRole::Owner->value ? 'her' : 'him';

        broadcast(new MemberAppearanceUpdated(
            (int) $membership->couple_id,
            $slot,
            $user->settings['appearance'] ?? null,
        ))->toOthers();
    }
}
