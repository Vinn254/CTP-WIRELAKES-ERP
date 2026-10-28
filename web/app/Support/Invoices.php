<?php

namespace App\Support;

use App\Models\FinanceEntry;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Invoicing rules. Staff raise an invoice and record what comes in; the money itself is only ever
 * counted on the money register, which each payment feeds with its own income line. A payment that was wrong is
 * voided with its reason and answered by an opposite register line, so the mistake stays visible.
 */
class Invoices
{
    public static function raise(Request $request): Invoice
    {
        $data = Invoice::dataFrom($request);

        $invoice = Invoice::create([
            ...$data,
            'reference' => Invoice::nextReference($data['issued_on']),
            'status' => 'open',
            'raised_by_user' => auth()->id(),
        ]);

        return $invoice;
    }

    /** Money received on an invoice — the only thing that moves an invoice towards settled. */
    public static function pay(Request $request, Invoice $invoice): InvoicePayment
    {
        abort_if($invoice->status === 'cancelled', 422,
            $invoice->reference.' was cancelled — a cancelled invoice is reopened by the office, never paid against.');

        $data = InvoicePayment::dataFrom($request);
        $balance = $invoice->balanceKsh();

        abort_if((float) $data['amount_ksh'] > $balance + 0.004, 422,
            'Only '.$invoice->balanceLabel().' is still owed on '.$invoice->reference
            .' — the payment cannot be more than what is outstanding.');

        // The payment and its register line are one action: a line that fails to post takes the payment with it,
        // so the invoice can never show money the register has not counted.
        $payment = DB::transaction(function () use ($invoice, $data) {
            $payment = $invoice->payments()->create([
                ...$data,
                'recorded_by_user' => auth()->id(),
            ]);

            FinanceEntry::post('invoice_settlement', $invoice->title.' — '.$invoice->memberName(),
                (float) $payment->amount_ksh, static::tie($invoice, $payment));

            return $payment;
        });

        return static::settle($invoice);
    }

    /** A payment that was wrong: the line stays, the money is taken back off the register with an opposite line. */
    public static function void(Request $request, InvoicePayment $payment): InvoicePayment
    {
        $data = $request->validate([
            'void_reason' => 'required|string|max:500',
        ], ['void_reason.required' => 'Say what was wrong with the payment — the reason stays on the invoice.']);

        abort_if($payment->isVoid(), 422, 'That payment is already voided.');

        $invoice = $payment->invoice;

        DB::transaction(function () use ($invoice, $payment, $data) {
            $payment->update([
                'voided_at' => now(),
                'void_reason' => $data['void_reason'],
                'voided_by_user' => auth()->id(),
            ]);

            FinanceEntry::post('invoice_settlement', 'Reversal — '.$invoice->title.' — '.$invoice->memberName(),
                -1 * (float) $payment->amount_ksh, [
                    ...static::tie($invoice, $payment),
                    'note' => 'Reverses the payment of '.$payment->amountLabel().' on '.$invoice->reference
                        .' recorded on '.$payment->happened_on->format('d M Y').': '.$data['void_reason'],
                ]);
        });

        return static::settle($invoice);
    }

    /** Cancelling an invoice is a money decision, so it is signed by a programme executive and keeps its reason. */
    public static function cancel(Request $request, Invoice $invoice): Invoice
    {
        Finance::requireRelease('Cancelling an invoice');

        $data = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], ['cancel_reason.required' => 'An invoice is only cancelled with the reason written on it.']);

        abort_if($invoice->status === 'cancelled', 422, $invoice->reference.' is already cancelled.');
        abort_if($invoice->paidKsh() > 0, 422, $invoice->reference.' has money paid against it — void the payments '
            .'first, so the register and the invoice still agree.');

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_by_user' => auth()->id(),
            'cancel_reason' => $data['cancel_reason'],
        ]);

        return $invoice;
    }

    /** A cancelled invoice that was cancelled wrongly is reopened; the record is never deleted or copied. */
    public static function reopen(Invoice $invoice): Invoice
    {
        Finance::requireRelease('Reopening an invoice');

        abort_if($invoice->status !== 'cancelled', 422, $invoice->reference.' is not cancelled.');

        $invoice->update(['status' => $invoice->balanceKsh() <= 0.004 ? 'settled' : 'open',
            'cancelled_by_user' => null, 'cancel_reason' => null]);

        return $invoice;
    }

    /** Settled is worked out from the payments, never typed in. */
    public static function settle(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'cancelled') {
            return $invoice;
        }

        $settled = $invoice->balanceKsh() <= 0.004;

        if (($invoice->status === 'settled') !== $settled) {
            $invoice->update(['status' => $settled ? 'settled' : 'open']);
        }

        return $invoice;
    }

    /** How a register line points back at the invoice and the member it was raised for. */
    private static function tie(Invoice $invoice, InvoicePayment $payment): array
    {
        return [
            'method' => $payment->method,
            'happened_on' => $payment->happened_on->toDateString(),
            'note' => trim($invoice->reference.' — '.($payment->reference ? $payment->reference.' ' : '')
                .($payment->note ?? 'payment received against the invoice')),
            'project_id' => $invoice->project_id,
            'ctp_id' => $invoice->ctp_id,
            'fundi_id' => $invoice->fundi_id,
            'source_type' => InvoicePayment::class,
            'source_id' => $payment->id,
        ];
    }
}
