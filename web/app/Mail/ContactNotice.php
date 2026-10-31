<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A line from the public Contact page, carried to the desk the sender asked for. The office always reads the same
 * screen, so the link points at it rather than at anything the mail server would have to authorise.
 */
class ContactNotice extends Mailable
{
    use Queueable;

    public function __construct(public ContactMessage $note, public string $link)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — website message: '.$this->note->topicLabel());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-notice', with: [
            'note' => $this->note,
            'link' => $this->link,
        ]);
    }
}
