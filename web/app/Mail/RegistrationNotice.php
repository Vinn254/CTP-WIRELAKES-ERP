<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The office hears that something new is on the register that is not a member record — a trainer posted at a site,
 * a hirer, a community, a person who answered a careers posting. Each kind describes itself, so the notice always
 * carries the lines the desk actually decides on.
 */
class RegistrationNotice extends Mailable
{
    use Queueable;

    /** @param  array{what:string,name:string,desk:string,rows:array<string,string>,link:string}  $card */
    public function __construct(public array $card)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — new '.$this->card['what'].': '.$this->card['name']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.registration', with: [
            'what' => $this->card['what'],
            'name' => $this->card['name'],
            'rows' => $this->card['rows'],
            'link' => $this->card['link'],
        ]);
    }
}
