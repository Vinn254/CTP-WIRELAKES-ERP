<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The office turned an application down, and the applicant is told why in the office's own words — a rejection that
 * never reaches them reads as silence, and they cannot correct what they were never told was wrong.
 */
class ApplicationRejected extends Mailable
{
    use Queueable;

    public function __construct(public string $what, public string $name, public string $reason)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.$this->what.' application — what the office decided');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.application-rejected', with: [
            'what' => $this->what,
            'name' => $this->name,
            'reason' => $this->reason,
        ]);
    }
}
