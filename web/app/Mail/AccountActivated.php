<?php

namespace App\Mail;

use App\Models\AccountInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The activation link of an approved member, posted to the address they registered with. The office screen has
 * always shown the link for copying; this is the same link reaching the person instead.
 */
class AccountActivated extends Mailable
{
    use Queueable;

    public function __construct(public AccountInvite $invite, public string $what)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->what.' approved — activate your account');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-activated', with: [
            'link' => route('activate.show', ['token' => $this->invite->token]),
            'invite' => $this->invite,
            'what' => $this->what,
            'code' => $this->invite->member()?->{$this->codeColumn()} ?? null,
        ]);
    }

    private function codeColumn(): string
    {
        return $this->invite->member_type === 'fundi' ? 'ef_code' : 'ctp_code';
    }
}
