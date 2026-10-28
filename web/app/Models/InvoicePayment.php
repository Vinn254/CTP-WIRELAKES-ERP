<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * One payment received against an invoice. Recording one is what posts the income line on the money register, so
 * the invoice and the register can never disagree. A payment that was wrong is voided with its reason — the line
 * stays on the invoice and an opposite line goes on the register.
 */
class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'amount_ksh', 'happened_on', 'method', 'reference', 'note',
        'voided_at', 'void_reason', 'recorded_by_user', 'voided_by_user',
    ];

    protected $casts = [
        'amount_ksh' => 'decimal:2',
        'happened_on' => 'date',
        'voided_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by_user');
    }

    /** The register line this payment put up, so the money and the invoice point at each other. */
    public function entry()
    {
        return $this->morphOne(FinanceEntry::class, 'source');
    }

    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'amount_ksh' => 'required|numeric|min:0.01|max:1000000000',
            'happened_on' => 'required|date',
            'method' => 'required|in:'.implode(',', array_keys(FinanceEntry::METHODS)),
            'reference' => 'nullable|string|max:60',
            'note' => 'nullable|string|max:500',
        ], [
            'amount_ksh.required' => 'Say how much was received — the balance is worked out from the payments.',
        ], [
            'amount_ksh' => 'Amount received',
            'happened_on' => 'Date received',
            'method' => 'How it was paid',
        ]);

        $data['reference'] = $data['reference'] ?? null;

        return $data;
    }

    public function amountLabel(): string
    {
        return Finance::ksh((float) $this->amount_ksh);
    }

    public function methodLabel(): string
    {
        return FinanceEntry::METHODS[$this->method] ?? $this->method;
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }
}
