<?php

namespace App\Mail;

use App\Models\PurchaseQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The quotation as the office holds it, sent back to the supplier to confirm the price and the terms stand. The
 * same branded page is printed for the file, so the paper and the email never say different figures.
 */
class QuotationEmail extends Mailable
{
    use Queueable;

    public function __construct(public PurchaseQuotation $quotation, public ?string $note = null)
    {
    }

    public function envelope(): Envelope
    {
        $q = $this->quotation;

        return new Envelope(subject: 'Quotation '.($q->quotation_ref ?: $q->request->reference)
            .' from '.($q->supplier->name ?? 'your firm').' — '.$q->amountLabel().' for '.$q->request->item_name
            .' ('.config('app.name').')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.quotation', with: [
            'quotation' => $this->quotation,
            'note' => $this->note,
        ]);
    }
}
