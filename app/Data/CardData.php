<?php

namespace App\Data;

use App\Models\Card;
use Spatie\LaravelData\Data;

class CardData extends Data
{
    public function __construct(
        public int $id,
        public int $columnId,
        public string $title,
        public ?string $description,
        public string $assignee, // him|her|together
        public ?string $dueAt,
        public bool $done,
        public int $position,
    ) {
    }

    public static function fromModel(Card $card): self
    {
        return new self(
            id: $card->id,
            columnId: $card->board_column_id,
            title: $card->title,
            description: $card->description,
            assignee: $card->assignee->value,
            dueAt: $card->due_at?->toIso8601String(),
            done: $card->done,
            position: $card->position,
        );
    }
}
