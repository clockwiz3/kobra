<?php

namespace App\Models;

use App\Enums\CoupleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SHARD ROOT — изолированный tenant. НЕ использует CoupleScoped (это сам shard).
 */
class Couple extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CoupleStatus::class,
            'settings' => 'array',
            'archived_at' => 'datetime',
            'cache_version' => 'integer',
        ];
    }

    /** Участники пары (ровно 2 при status=active). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'couple_user')
            ->withPivot(['role', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CoupleInvitation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === CoupleStatus::Active;
    }
}
