<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Участник пары в привязке к слоту исполнителя карточки (him/her). Фронт показывает name
 * вместо обезличенных «Он/Она». Слот выводится из роли в паре (стабилен, переживает
 * переименование аккаунта): owner → her, partner → him — соответствует исторической
 * разметке карточек закрытого запуска (Леся=owner=her, Серёжа=partner=him).
 */
class BoardMemberData extends Data
{
    public function __construct(
        public string $slot, // 'him' | 'her' (CardAssignee без 'together')
        public int $id,
        public ?string $name,
        /** @var array<string, mixed>|null «внешний вид» участника (рамка карточки), из User.settings['appearance'] */
        public ?array $appearance,
    ) {
    }
}
