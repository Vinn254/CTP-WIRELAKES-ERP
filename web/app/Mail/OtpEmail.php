<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one-time code. The code itself is only ever shown to the person who asked for it, in this email.
 */
class OtpEmail extends Mailable
{
    use Queueable;

    public function __construct(public string $code, public string $purpose)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === 'login'
                ? config('app.name').' — your sign-in code'
                : config('app.name').' — confirm your new account',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.otp', with: [
            'code' => $this->code,
            'minutes' => (int) config('account.otp.minutes'),
            'login' => $this->purpose === 'login',
        ]);
    }
}
