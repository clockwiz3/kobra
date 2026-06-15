<?php

namespace App\Models;

use App\Concerns\CoupleScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * COUPLE-OWNED, один на shard. Общая прогрессия пары (xp/level/hearts/streak). CoupleScoped.
 */
class GamificationProfile extends Model
{
    use CoupleScoped;

    protected $guarded = ['id', 'couple_id'];

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'level' => 'integer',
            'hearts_balance' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'streak_last_qualified_on' => 'date',
            'last_processed_event_id' => 'integer',
            'settings' => 'array',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }
}
