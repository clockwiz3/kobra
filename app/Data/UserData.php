<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

/** Публичный профиль юзера наружу (→ TS-тип). Без чувствительных полей. */
class UserData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?string $avatar,
        /** @var array<string, mixed>|null per-user «внешний вид» (рамка карточек), из settings['appearance'] */
        public ?array $appearance,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            avatar: $user->avatar,
            appearance: $user->settings['appearance'] ?? null,
        );
    }
}
