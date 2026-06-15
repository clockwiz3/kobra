<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramLinkService;
use App\Services\Telegram\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Telegram webhook. Секьюрится secret-токеном (header X-Telegram-Bot-Api-Secret-Token).
 * Команды: /start <link_token> — привязка, /stop — отвязка, прочее — help.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramService $tg, TelegramLinkService $linker): Response
    {
        $secret = config('services.telegram.webhook_secret');
        if (! empty($secret) && ! hash_equals((string) $secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            Log::warning('[Telegram.webhook] rejected: bad secret');
            abort(403);
        }

        $message = $request->input('message');
        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === null) {
            return response()->noContent();
        }
        $chatId = (int) $chatId;

        Log::debug('[Telegram.webhook] update', ['chat' => '***'.substr((string) $chatId, -3), 'cmd' => explode(' ', $text)[0] ?? '']);

        if (str_starts_with($text, '/start')) {
            $startToken = trim(substr($text, strlen('/start')));
            if ($startToken !== '') {
                $link = $linker->link($startToken, $chatId);
                $tg->sendMessage($chatId, $link !== null
                    ? 'Готово! Уведомления Cuploid подключены ♥'
                    : 'Ссылка недействительна или устарела.');
            } else {
                $tg->sendMessage($chatId, 'Привет! Это бот Cuploid. Открой ссылку привязки из приложения.');
            }
        } elseif (str_starts_with($text, '/stop')) {
            $linker->unlink($chatId);
            $tg->sendMessage($chatId, 'Уведомления отключены.');
        } else {
            $tg->sendMessage($chatId, 'Команды: /start &lt;код&gt; — подключить, /stop — отключить.');
        }

        return response()->noContent();
    }
}
