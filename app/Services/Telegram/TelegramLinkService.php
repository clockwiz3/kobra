<?php

namespace App\Services\Telegram;

use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Привязка/отвязка Telegram по deep-link токену.
 */
class TelegramLinkService
{
    /** Привязать telegram_id по /start <token>. null = токен невалиден/просрочен. */
    public function link(string $token, int $telegramId): ?TelegramLinkToken
    {
        $link = TelegramLinkToken::where('token', $token)->first();

        if ($link === null || ! $link->isPending() || $link->isExpired()) {
            Log::warning('[Telegram.link] invalid or expired token');

            return null;
        }

        return DB::transaction(function () use ($link, $telegramId) {
            $link->forceFill(['telegram_id' => $telegramId, 'status' => 'linked'])->save();

            if ($link->user_id !== null) {
                User::whereKey($link->user_id)->update(['telegram_id' => $telegramId]);
            }
            if ($link->waitlist_entry_id !== null) {
                WaitlistEntry::whereKey($link->waitlist_entry_id)->update(['telegram_id' => $telegramId]);
            }

            Log::info('[Telegram.link] linked', [
                'link_id' => $link->id,
                'telegram_id' => '***'.substr((string) $telegramId, -3),
            ]);

            return $link;
        });
    }

    /** Отвязать: убрать telegram_id у юзера/вайтлиста по chat id. */
    public function unlink(int $telegramId): void
    {
        DB::transaction(function () use ($telegramId) {
            User::where('telegram_id', $telegramId)->update(['telegram_id' => null]);
            WaitlistEntry::where('telegram_id', $telegramId)->update(['telegram_id' => null]);
        });
        Log::info('[Telegram.unlink] unlinked', ['telegram_id' => '***'.substr((string) $telegramId, -3)]);
    }
}
