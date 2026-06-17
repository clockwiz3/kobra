<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/** Ответ логина: plain-text токен + публичный профиль (→ TS-тип). */
class AuthSessionData extends Data
{
    public function __construct(
        public string $token,
        public UserData $user,
    ) {}
}
