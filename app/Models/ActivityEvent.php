<?php

namespace App\Models;

use App\Concerns\CoupleScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * COUPLE-OWNED, append-only. Лента событий пары (топливо геймификации). CoupleScoped.
 */
class ActivityEvent extends Model
{
    use CoupleScoped;

    protected $guarded = ['id', 'couple_id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
