<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * OUTBOX-уведомление (таблица notification_outbox). Полиморфный notifiable (User|WaitlistEntry via morphMap).
 * НЕ CoupleScoped — доставка роутится по notifiable; couple_id лишь для scoped admin/purge.
 */
class OutboxNotification extends Model
{
    protected $table = 'notification_outbox';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** Готовые к отправке (pending + время наступило). */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', NotificationStatus::Pending->value)
            ->where(function (Builder $q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            });
    }

    public function markSent(): void
    {
        $this->forceFill(['status' => NotificationStatus::Sent, 'sent_at' => now()])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => NotificationStatus::Failed,
            'attempts' => $this->attempts + 1,
            'last_error' => $error,
        ])->save();
    }
}
