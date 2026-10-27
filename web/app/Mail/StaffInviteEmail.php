<?php

namespace App\Mail;

use App\Models\StaffInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The activation link, carried to the person it was raised for. The office screen has always shown the link for
 * copying; this is the same link posted to the address instead.
 */
class StaffInviteEmail extends Mailable
{
    use Queueable;

    public function __construct(public StaffInvite $invite)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — you have been invited to the office');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.staff-invite', with: [
            'link' => route('invite.show', ['token' => $this->invite->token]),
            'invite' => $this->invite,
        ]);
    }
}
