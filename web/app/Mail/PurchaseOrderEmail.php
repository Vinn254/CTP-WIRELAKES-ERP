<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The purchase order itself, carried to the supplier's address. The document is the email — the same branded page
 * the office prints and hands over — so the supplier always has the order number the delivery note and their own
 * invoice have to quote.
 */
class PurchaseOrderEmail extends Mailable
{
    use Queueable;

    public function __construct(public PurchaseOrder $order, public ?string $note = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    private function subjectLine(): string
    {
        $o = $this->order;

        return 'Purchase order '.$o->reference.' from '.config('app.name').' — '
            .($o->supplier->name ?? 'your firm').', '.$o->request->item_name.' ('.$o->orderedLabel()
            .') for '.$o->amountLabel();
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order', with: [
            'order' => $this->order,
            'note' => $this->note,
        ]);
    }
}
