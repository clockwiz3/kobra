<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\OutboxNotification;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\TelegramChannel;
use App\Services\Notifications\Contracts\NotificationChannelContract;
use App\Support\CoupleContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Доставка одного outbox-уведомления. Идемпотентно (sent/failed не переотправляются),
 * retriable (attempts/backoff). Couple-scoped рендер — через CoupleContext (set/clear).
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $notificationId)
    {
    }

    public function handle(CoupleContext $context): void
    {
        $n = OutboxNotification::find($this->notificationId);

        // Идемпотентно: уже доставлено / терминально упало / удалено.
        if ($n === null || in_array($n->status, [NotificationStatus::Sent, NotificationStatus::Failed], true)) {
            return;
        }
        // Запланировано на будущее — пропустить (подберёт dispatch-due позже).
        if ($n->scheduled_at !== null && $n->scheduled_at->isFuture()) {
            return;
        }

        try {
            if ($n->couple_id !== null) {
                $context->set($n->couple_id);
            }

            $this->channelFor($n->channel)->send($n);
            $n->markSent();
            Log::info('[Dispatch.send] sent', ['notification_id' => $n->id, 'channel' => $n->channel->value]);
        } catch (Throwable $e) {
            $n->attempts += 1;
            $n->last_error = $e->getMessage();
            if ($n->attempts >= $this->tries) {
                $n->status = NotificationStatus::Failed; // терминально
            }
            $n->save();
            Log::warning('[Dispatch.send] failed', ['notification_id' => $n->id, 'attempt' => $n->attempts, 'error' => $e->getMessage()]);
            throw $e; // отдать очереди на retry (если не sync)
        } finally {
            if ($n->couple_id !== null) {
                $context->clear();
            }
        }
    }

    private function channelFor(NotificationChannel $channel): NotificationChannelContract
    {
        return match ($channel) {
            NotificationChannel::Email => app(EmailChannel::class),
            NotificationChannel::Telegram => app(TelegramChannel::class),
            NotificationChannel::Push => throw new RuntimeException('Push channel not implemented yet.'),
        };
    }
}
