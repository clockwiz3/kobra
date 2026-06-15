<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $confirmUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Подтвердите подписку — Cuploid ♥');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.waitlist-confirmation');
    }
}
