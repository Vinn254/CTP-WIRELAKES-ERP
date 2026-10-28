<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The invoice itself, carried to the address the office typed on it. The document is the email — the same branded
 * page the office prints — so the payer always has the Paybill and the reference in front of them.
 */
class InvoiceEmail extends Mailable
{
    use Queueable;

    public function __construct(public Invoice $invoice)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    private function subjectLine(): string
    {
        $i = $this->invoice;

        return 'Invoice '.$i->reference.' from '.config('app.name').' — '
            .match ($i->status) {
                'settled' => 'settled in full',
                'cancelled' => 'withdrawn',
                default => $i->balanceLabel().' outstanding'.($i->due_on ? ' by '.$i->due_on->format('d M Y') : ''),
            };
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice', with: [
            'invoice' => $this->invoice,
        ]);
    }
}
