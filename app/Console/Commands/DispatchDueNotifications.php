<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\OutboxNotification;
use Illuminate\Console\Command;

/**
 * Подобрать готовые к отправке (scheduled/retriable) уведомления из outbox.
 * Запускается планировщиком; immediate-доставку делает NotificationDispatcher::enqueue.
 */
class DispatchDueNotifications extends Command
{
    protected $signature = 'notifications:dispatch-due {--limit=200}';

    protected $description = 'Диспатчить готовые к отправке уведомления из outbox';

    public function handle(): int
    {
        $count = 0;
        OutboxNotification::due()
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (OutboxNotification $n) use (&$count): void {
                SendNotificationJob::dispatch($n->id);
                $count++;
            });

        $this->info("Dispatched {$count} notification(s).");

        return self::SUCCESS;
    }
}
