<?php

namespace App\Data;

use App\Models\BoardColumn;
use Spatie\LaravelData\Data;

class ColumnData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public int $position,
        public bool $isTerminal, // терминальная («Готово») колонка: попадание карточки = «выполнено»
        /** @var CardData[] */
        public array $cards,
    ) {
    }

    public static function fromModel(BoardColumn $column): self
    {
        return new self(
            id: $column->id,
            title: $column->title,
            position: $column->position,
            isTerminal: $column->is_terminal,
            cards: $column->cards->map(fn ($c) => CardData::fromModel($c))->all(),
        );
    }
}
