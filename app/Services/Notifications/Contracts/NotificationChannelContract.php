<?php

namespace App\Services\Notifications\Contracts;

use App\Enums\NotificationChannel;
use App\Models\OutboxNotification;

/**
 * Контракт канала доставки. Реализации (EmailChannel, TelegramChannel) — в задаче #11.
 */
interface NotificationChannelContract
{
    /** Какой канал обслуживает реализация. */
    public function channel(): NotificationChannel;

    /** Отправить уведомление. Бросает исключение при сбое (воркер пометит failed/ретрайнет). */
    public function send(OutboxNotification $notification): void;
}
