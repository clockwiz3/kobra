<?php

namespace App\Services\Waitlist;

use App\Enums\NotificationChannel;
use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Вайтлист: double-opt-in. БЕЗ рефералов/лидерборда. Идемпотентно, без enumeration.
 * Позиция — производная статичная (не хранится).
 */
class WaitlistService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    /**
     * Идемпотентная запись: новая → создать; существующая pending → пере-отправить подтверждение.
     * Никогда не раскрывает факт существования (контроллер отдаёт generic-ответ).
     */
    public function join(array $data): WaitlistEntry
    {
        $entry = WaitlistEntry::firstOrNew(['email' => $data['email']]);

        if (! $entry->exists) {
            $entry->fill([
                'name' => $data['name'] ?? null,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'locale' => $data['locale'] ?? null,
                'ip' => $data['ip'] ?? null,
            ]);
            $entry->status = WaitlistStatus::Pending; // confirmation_token проставит booted()
            $entry->save();
            Log::info('[WaitlistService.join] new entry', ['entry_id' => $entry->id]);
        } else {
            Log::debug('[WaitlistService.join] existing entry (idempotent)', ['entry_id' => $entry->id, 'status' => $entry->status->value]);
        }

        if ($entry->status === WaitlistStatus::Pending) {
            $this->dispatcher->enqueue($entry, NotificationChannel::Email, 'waitlist_confirmation', [
                'token' => $entry->confirmation_token,
            ]);
            if ($entry->telegram_id !== null) {
                $this->dispatcher->enqueue($entry, NotificationChannel::Telegram, 'waitlist_confirmation', [
                    'token' => $entry->confirmation_token,
                ]);
            }
        }

        return $entry;
    }

    /** Подтверждение по токену. Идемпотентно: повторный клик — no-op с confirmed. null = не найдено. */
    public function confirm(string $token): ?WaitlistEntry
    {
        $entry = WaitlistEntry::where('confirmation_token', $token)->first();
        if ($entry === null) {
            Log::warning('[WaitlistService.confirm] token not found');

            return null;
        }

        if ($entry->status === WaitlistStatus::Pending) {
            $entry->forceFill(['status' => WaitlistStatus::Confirmed, 'confirmed_at' => now()])->save();
            Log::info('[WaitlistService.confirm] confirmed', ['entry_id' => $entry->id]);
        }

        return $entry;
    }

    /** Статус по токену + статичная позиция (если подтверждён). null = не найдено. */
    public function status(string $token): ?array
    {
        $entry = WaitlistEntry::where('confirmation_token', $token)->first();
        if ($entry === null) {
            return null;
        }

        $position = null;
        if ($entry->isConfirmed()) {
            // монотонна, не зависит от чужих сигнапов/удалений
            $position = WaitlistEntry::where('status', WaitlistStatus::Confirmed->value)
                ->where('id', '<=', $entry->id)
                ->count();
        }

        return ['confirmed' => $entry->isConfirmed(), 'static_position' => $position];
    }
}
