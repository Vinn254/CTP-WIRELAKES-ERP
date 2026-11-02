<?php

namespace App\Mail;

use App\Models\FundiRegistration;
use App\Models\Setting;
use App\Support\Finance;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The note a CTP or E-Fundi applicant gets the moment their application lands. It carries the registration fee, the
 * Paybill it goes to, and the order the office works in: the money first, then the System Administrator reads the
 * application and approves it.
 */
class RegistrationReceived extends Mailable
{
    use Queueable;

    public function __construct(public Model $member, public string $what)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' — your '.$this->what
            .' application is received ('.$this->member->reference.')');
    }

    public function content(): Content
    {
        $class = get_class($this->member);

        return new Content(view: 'mail.registration-received', with: [
            'member' => $this->member,
            'what' => $this->what,
            'fee' => $class::feeAmount(),
            'grade' => $this->member instanceof FundiRegistration ? $this->member->gradeLabel() : 'CTP',
            'paybill' => Finance::paybill(),
            'needsPay' => $this->member->fee_status === 'awaiting_payment',
            'sponsor' => $this->member->sponsorSummary(),
            'support' => Setting::get('programme_email', content('site', 'contact', 'email', '')),
            'phone' => Setting::get('programme_phone', content('site', 'contact', 'phone', '')),
        ]);
    }
}
