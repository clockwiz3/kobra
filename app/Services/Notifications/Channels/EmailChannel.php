<?php

namespace App\Services\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Mail\WaitlistConfirmationMail;
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
        $notifiable = $notification->notifiable; // User | WaitlistEntry
        $email = $notifiable?->email;
        if (empty($email)) {
            throw new RuntimeException('No email address for notifiable.');
        }

        match ($notification->type) {
            'waitlist_confirmation' => Mail::to($email)->send(
                new WaitlistConfirmationMail($this->confirmUrl($notification)),
            ),
            default => Mail::raw($this->genericText($notification), function ($m) use ($email) {
                $m->to($email)->subject('Cuploid ♥');
            }),
        };
    }

    private function confirmUrl(OutboxNotification $notification): string
    {
        $token = $notification->payload['token'] ?? '';

        return rtrim((string) config('app.url'), '/').'/confirm/'.$token;
    }

    private function genericText(OutboxNotification $notification): string
    {
        return $notification->payload['message'] ?? 'У вас новое уведомление от Cuploid.';
    }
}
