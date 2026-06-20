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
        public string $priority, // low|normal|high|critical
        public ?string $dueAt,    // ISO 8601 — одиночная дата либо начало диапазона
        public ?string $dueEndAt, // ISO 8601 — конец диапазона (null = одиночная дата)
        public ?string $dueTime,  // 'HH:MM' — время суток (null = без времени)
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
            priority: $card->priority->value,
            dueAt: $card->due_at?->toIso8601String(),
            dueEndAt: $card->due_end_at?->toIso8601String(),
            dueTime: $card->due_time,
            done: $card->done,
            position: $card->position,
        );
    }
}
