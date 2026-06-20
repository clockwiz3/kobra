<?php

namespace App\Models;

use App\Concerns\CoupleScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** COUPLE-OWNED. Колонка доски. CoupleScoped. */
class BoardColumn extends Model
{
    use CoupleScoped;

    protected $guarded = ['id', 'couple_id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_terminal' => 'boolean'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function cards(): HasMany
    {
        // Только карточки НА доске; «убранные с доски» (on_board=false, бэклог) исключены везде,
        // где используется этот relation: выборка доски, счётчик позиции при создании и т.п.
        return $this->hasMany(Card::class)->where('on_board', true)->orderBy('position');
    }
}
