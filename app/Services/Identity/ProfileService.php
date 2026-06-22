<?php

namespace App\Services\Identity;

use App\Models\User;
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

        if (isset($data['appearance']['card'])) {
            $settings = $user->settings ?? [];
            $settings['appearance']['card'] = $data['appearance']['card']; // атомарная замена card
            $user->settings = $settings;
        }

        $user->save();
        Log::info('[ProfileService.updateProfile] saved', ['user_id' => $user->id, 'keys' => array_keys($data)]);

        return $user;
    }
}
