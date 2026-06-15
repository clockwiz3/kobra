<?php

namespace App\Models;

use App\Concerns\CoupleScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** COUPLE-OWNED. Доска пары. CoupleScoped. */
class Board extends Model
{
    use CoupleScoped;

    protected $guarded = ['id', 'couple_id'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }
}
