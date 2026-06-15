<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GLOBAL каталог ачивок. НЕ CoupleScoped (общие определения).
 */
class Achievement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'criteria' => 'array',
        ];
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(AchievementUnlock::class);
    }
}
