<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент Telegram Bot API: отправка сообщений, установка webhook, deep-link.
 */
class TelegramService
{
    private function token(): ?string
    {
        return config('services.telegram.bot_token');
    }

    public function sendMessage(int $chatId, string $text): bool
    {
        $token = $this->token();
        if (empty($token)) {
            Log::warning('[Telegram.send] no bot token configured — skipped', ['chat' => $this->mask($chatId)]);

            return false;
        }

        $res = Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        if (! $res->successful()) {
            Log::error('[Telegram.send] failed', ['status' => $res->status(), 'body' => $res->body()]);

            return false;
        }

        return true;
    }

    public function setWebhook(string $url, ?string $secret): array
    {
        $token = $this->token();
        if (empty($token)) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN not set'];
        }

        return Http::asJson()->post("https://api.telegram.org/bot{$token}/setWebhook", array_filter([
            'url' => $url,
            'secret_token' => $secret,
        ]))->json() ?? [];
    }

    public function deepLink(string $startToken): string
    {
        $bot = config('services.telegram.bot_username', 'cuploid_bot');

        return "https://t.me/{$bot}?start={$startToken}";
    }

    private function mask(int $chatId): string
    {
        return '***'.substr((string) $chatId, -3);
    }
}
