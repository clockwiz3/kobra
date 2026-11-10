<?php

namespace App\Services\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Models\OutboxNotification;
use App\Services\Notifications\Contracts\NotificationChannelContract;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailChannel implements NotificationChannelContract
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }

    public function send(OutboxNotification $notification): void
    {
        $notifiable = $notification->notifiable;
        $email = $notifiable?->email;
        if (empty($email)) {
            throw new RuntimeException('No email address for notifiable.');
        }

        Mail::raw($this->genericText($notification), function ($m) use ($email) {
            $m->to($email)->subject('Kobra ♥');
        });
    }

    private function genericText(OutboxNotification $notification): string
    {
        return $notification->payload['message'] ?? 'Новое уведомление в Kobra.';
    }
}
