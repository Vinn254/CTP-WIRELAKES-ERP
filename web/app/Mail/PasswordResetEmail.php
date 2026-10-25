<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The password reset link, with the minutes it has left spelled out so nobody keeps an old one.
 */
class PasswordResetEmail extends Mailable
{
    use Queueable;

    public function __construct(public string $url, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — reset your password');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reset-link', with: [
            'url' => $this->url,
            'minutes' => $this->minutes,
        ]);
    }
}
