<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Модуль Identity: проверка кредов и выдача/отзыв Sanctum personal access token.
 * Анти-enumeration: любой провал (нет юзера / passwordless / неверный пароль) → единый null.
 */
class AuthService
{
    /**
     * Проверить email+пароль и выдать токен. null при любом провале (без различия причин).
     *
     * @return array{token: string, user: User}|null
     */
    public function attempt(string $email, string $password): ?array
    {
        Log::debug('[AuthService.attempt] start'); // без email/пароля (PII)

        $user = User::where('email', $email)->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            // email допустим на WARN (security-аномалия), пароль — никогда.
            Log::warning('[AuthService.attempt] login failed', ['email' => $email]);

            return null;
        }

        $token = $user->createToken('spa')->plainTextToken;
        Log::info('[AuthService.attempt] login success', ['user_id' => $user->id]);

        return ['token' => $token, 'user' => $user];
    }

    /** Отозвать текущий access-токен (logout). */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
        Log::info('[AuthService.logout] token revoked', ['user_id' => $user->id]);
    }
}
