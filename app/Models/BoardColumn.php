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
        return $this->hasMany(Card::class)->orderBy('position');
    }
}
