<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\OutboxNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Кладёт уведомление в outbox (status=pending). Реальную доставку из outbox
 * (idempotent/retriable воркер + каналы) делает задача #11.
 */
class NotificationDispatcher
{
    public function enqueue(
        Model $notifiable,
        NotificationChannel $channel,
        string $type,
        array $payload = [],
        ?Carbon $scheduledAt = null,
        ?int $coupleId = null,
    ): OutboxNotification {
        $notification = new OutboxNotification([
            'channel' => $channel,
            'type' => $type,
            'payload' => $payload !== [] ? $payload : null,
            'status' => NotificationStatus::Pending,
            'scheduled_at' => $scheduledAt,
            'couple_id' => $coupleId,
        ]);
        $notification->notifiable()->associate($notifiable);
        $notification->save();

        // Доставка из outbox (afterCommit — чтобы строка точно была закоммичена).
        SendNotificationJob::dispatch($notification->id)->afterCommit();

        Log::info('[Outbox.enqueue] queued', [
            'notification_id' => $notification->id,
            'type' => $type,
            'channel' => $channel->value,
            'notifiable' => $notifiable->getMorphClass().'#'.$notifiable->getKey(),
        ]);

        return $notification;
    }
}
