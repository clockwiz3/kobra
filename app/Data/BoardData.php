<?php

namespace App\Data;

use App\Models\Board;
use Spatie\LaravelData\Data;

class BoardData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var ColumnData[] */
        public array $columns,
    ) {
    }

    public static function fromModel(Board $board): self
    {
        return new self(
            id: $board->id,
            name: $board->name,
            columns: $board->columns->map(fn ($col) => ColumnData::fromModel($col))->all(),
        );
    }
}
