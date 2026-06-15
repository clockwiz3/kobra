<?php

namespace App\Services\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Models\OutboxNotification;
use App\Services\Notifications\Contracts\NotificationChannelContract;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramChannel implements NotificationChannelContract
{
    public function __construct(private readonly TelegramService $telegram)
    {
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::Telegram;
    }

    public function send(OutboxNotification $notification): void
    {
        $telegramId = $notification->notifiable?->telegram_id;
        if ($telegramId === null) {
            // Telegram не привязан — нечего слать (no-op success).
            Log::debug('[TelegramChannel.send] skip: no telegram_id', ['notification_id' => $notification->id]);

            return;
        }

        $text = match ($notification->type) {
            'waitlist_confirmation' => 'Подтвердите подписку Cuploid ♥: '
                .rtrim((string) config('app.url'), '/').'/confirm/'.($notification->payload['token'] ?? ''),
            default => $notification->payload['message'] ?? 'Новое уведомление от Cuploid ♥',
        };

        if (! $this->telegram->sendMessage((int) $telegramId, $text)) {
            throw new RuntimeException('Telegram send failed.');
        }
    }
}
