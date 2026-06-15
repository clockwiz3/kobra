<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url?}';

    protected $description = 'Зарегистрировать webhook Telegram-бота';

    public function handle(TelegramService $tg): int
    {
        $url = $this->argument('url') ?? rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
        $secret = config('services.telegram.webhook_secret');

        $result = $tg->setWebhook($url, $secret !== null ? (string) $secret : null);
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
