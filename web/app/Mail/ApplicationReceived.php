<?php

namespace App\Mail;

use App\Models\ProgrammeApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The acknowledgement a person gets the moment their application lands on an area page: it is with the office, and
 * the office answers within the window the page promises.
 */
class ApplicationReceived extends Mailable
{
    use Queueable;

    public function __construct(public ProgrammeApplication $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — we have received your request ('
            .$this->application->reference.')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.application-received', with: [
            'application' => $this->application,
            'support' => \App\Models\Setting::get('programme_email', content('site', 'contact', 'email', '')),
            'phone' => \App\Models\Setting::get('programme_phone', content('site', 'contact', 'phone', '')),
        ]);
    }
}
