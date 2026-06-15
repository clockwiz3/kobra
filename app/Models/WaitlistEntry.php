<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Запись вайтлиста. PRE-ACCOUNT, НЕ CoupleScoped. Без рефералов/позиции (хранимой).
 * Полиморфный notifiable для outbox через morphMap-алиас 'waitlist'.
 */
class WaitlistEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WaitlistEntry $entry): void {
            if ($entry->confirmation_token === null && $entry->status === WaitlistStatus::Pending) {
                $entry->confirmation_token = Str::lower(Str::random(40));
            }
        });
    }

    public function isConfirmed(): bool
    {
        return $this->status === WaitlistStatus::Confirmed;
    }
}
