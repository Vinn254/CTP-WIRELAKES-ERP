<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 11 — a purchase order. One order goes to one supplier for one approved request: the
 * delivery note the storekeeper signs and the invoice the supplier sends are both recorded on this row, so the
 * match in step 7 is three figures on one page rather than three pieces of paper in three drawers.
 */
class PurchaseOrder extends Model
{
    public const STATUSES = [
        'draft' => 'Raised — not yet sent to the supplier',
        'issued' => 'Sent to the supplier',
        'received' => 'Goods received and delivery note signed',
        'matched' => 'PO, delivery note and invoice matched — payment requested',
        'paid' => 'Supplier paid and recorded',
    ];

    protected $fillable = [
        'reference', 'purchase_request_id', 'supplier_id', 'quantity', 'unit_cost_ksh', 'amount_ksh', 'note',
        'raised_by_user', 'issued_on', 'delivery_note_ref', 'received_qty', 'receipt_note',
        'received_by_user', 'received_on', 'invoice_ref', 'invoice_amount_ksh', 'matched_by_user', 'matched_on',
        'paid_on', 'status', 'emailed_at', 'sent_to', 'sent_note',
    ];

    protected $casts = [
        'unit_cost_ksh' => 'decimal:2',
        'amount_ksh' => 'decimal:2',
        'invoice_amount_ksh' => 'decimal:2',
        'issued_on' => 'datetime',
        'received_on' => 'datetime',
        'matched_on' => 'datetime',
        'emailed_at' => 'datetime',
        'paid_on' => 'date',
    ];

    public static function issueDataFrom(Request $request): array
    {
        return $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'quantity' => 'required|numeric|min:0.01|max:1000000',
            'unit_cost_ksh' => 'required|numeric|min:0|max:100000000',
            'note' => 'nullable|string|max:1000',
        ], [
            'supplier_id.required' => 'A purchase order names the supplier it goes to.',
        ], [
            'supplier_id' => 'Supplier',
            'quantity' => 'Quantity ordered',
            'unit_cost_ksh' => 'Unit price agreed',
        ]);
    }

    public static function receiveDataFrom(Request $request): array
    {
        return $request->validate([
            'received_qty' => 'required|numeric|min:0.01|max:1000000',
            'delivery_note_ref' => 'required|string|max:80',
            'receipt_note' => 'nullable|string|max:1000',
        ], [
            'delivery_note_ref.required' => 'The storekeeper signs against a delivery note — its number is what the invoice is matched to.',
        ], [
            'received_qty' => 'Quantity received',
            'delivery_note_ref' => 'Delivery note number',
            'receipt_note' => 'Quality check',
        ]);
    }

    public static function matchDataFrom(Request $request): array
    {
        return $request->validate([
            'invoice_ref' => 'required|string|max:80',
            'invoice_amount_ksh' => 'required|numeric|min:0.01|max:1000000000',
        ], [], [
            'invoice_ref' => 'Invoice number',
            'invoice_amount_ksh' => 'Invoice amount',
        ]);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user')->withDefault();
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function orderedLabel(): string
    {
        return $this->numberLabel($this->quantity);
    }

    public function receivedLabel(): string
    {
        return $this->received_qty === null ? 'not counted in yet' : $this->numberLabel($this->received_qty);
    }

    public function numberLabel(?float $value): string
    {
        $n = rtrim(rtrim(number_format((float) $value, 2), '0'), '.');

        return $n.' '.($this->request->unit ?? 'pcs');
    }

    public function amountLabel(): string
    {
        return Finance::ksh((float) $this->amount_ksh);
    }

    public function invoiceLabel(): string
    {
        return $this->invoice_amount_ksh !== null ? Finance::ksh((float) $this->invoice_amount_ksh) : 'invoice not in';
    }

    /** Step 7 — the three figures that have to agree before a payment request is raised. */
    public function paperworkMatches(): bool
    {
        return $this->invoice_amount_ksh !== null
            && bccomp((string) $this->invoice_amount_ksh, (string) $this->amount_ksh, 2) === 0;
    }

    public function mustBe(string ...$statuses): void
    {
        if (in_array($this->status, $statuses, true)) {
            return;
        }

        $wanted = collect($statuses)->map(fn ($s) => self::STATUSES[$s] ?? $s)->join(' or ');

        abort(422, $this->reference.' is '.$this->statusLabel().', so that action is not open on it yet. '
            .'It has to be: '.$wanted.'.');
    }

    /** PO-2026-00002 — the number the supplier's delivery note and invoice are quoted against. */
    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'PO-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
