<?php

namespace App\Mail;

use App\Models\ProgrammeApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The office hears about an application the way it hears about a new member: the queue is not stared at all day,
 * so the line is announced to the executive and to the desk whose area it came from.
 */
class ApplicationNotice extends Mailable
{
    use Queueable;

    public function __construct(public ProgrammeApplication $application, public string $link)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — new service request: '.$this->application->areaLabel()
            .' · '.$this->application->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.application-notice', with: [
            'application' => $this->application,
            'link' => $this->link,
        ]);
    }
}
