<?php

namespace App\Models;

use App\Concerns\CoupleScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** COUPLE-OWNED. Метка/тег пары. CoupleScoped. */
class Label extends Model
{
    use CoupleScoped;

    protected $guarded = ['id', 'couple_id'];

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_label')->withTimestamps();
    }
}
