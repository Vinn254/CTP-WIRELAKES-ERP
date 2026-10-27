<?php

namespace App\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The office hears that a person joined. A new CTP or E-Fundi waits in a review queue nobody is staring at,
 * so the record is announced to the programme's super admins instead.
 */
class NewMemberNotice extends Mailable
{
    use Queueable;

    public function __construct(public Model $member, public string $what)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — new '.$this->what.' application: '.$this->member->full_name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-member', with: [
            'member' => $this->member,
            'what' => $this->what,
            'link' => route($this->what === 'CTP' ? 'admin.promoters.show' : 'admin.fundis.show', $this->member->id),
        ]);
    }
}
