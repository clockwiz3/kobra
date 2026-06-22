<?php

namespace App\Data;

use App\Enums\CoupleRole;
use App\Models\Board;
use Spatie\LaravelData\Data;

class BoardData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var ColumnData[] */
        public array $columns,
        /** @var BoardMemberData[] участники пары (him/her) — фронт показывает имена вместо «Он/Она» */
        public array $members,
    ) {
    }

    public static function fromModel(Board $board): self
    {
        return new self(
            id: $board->id,
            name: $board->name,
            columns: $board->columns->map(fn ($col) => ColumnData::fromModel($col))->all(),
            members: self::membersFor($board),
        );
    }

    /** Участники пары → слоты исполнителя. owner→her, partner→him (см. BoardMemberData). */
    private static function membersFor(Board $board): array
    {
        $couple = $board->relationLoaded('couple') ? $board->couple : $board->couple()->with('members')->first();
        if ($couple === null) {
            return [];
        }

        $slotByRole = [CoupleRole::Owner->value => 'her', CoupleRole::Partner->value => 'him'];

        return $couple->members
            ->map(fn ($user) => new BoardMemberData(
                slot: $slotByRole[$user->pivot->role] ?? 'her',
                id: $user->id,
                name: $user->name,
                appearance: $user->settings['appearance'] ?? null,
            ))
            ->all();
    }
}
