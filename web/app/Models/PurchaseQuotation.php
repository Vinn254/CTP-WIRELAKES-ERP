<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 11 — one quotation on a purchase request. Prices are attached as evidence of what the
 * market charged, so a quote is never edited into another figure: a wrong one is removed and the supplier quotes
 * again. Exactly one quote on a request is the one bought against.
 */
class PurchaseQuotation extends Model
{
    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'amount_ksh', 'quotation_ref', 'quoted_on',
        'note', 'is_selected', 'added_by_user',
    ];

    protected $casts = ['amount_ksh' => 'decimal:2', 'quoted_on' => 'date', 'is_selected' => 'boolean'];

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'amount_ksh' => 'required|numeric|min:0.01|max:1000000000',
            'quotation_ref' => 'nullable|string|max:80',
            'quoted_on' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ], [
            'supplier_id.required' => 'Quotations only count when they come from a supplier on the approved list.',
        ], [
            'supplier_id' => 'Supplier',
            'amount_ksh' => 'Quoted price',
            'quotation_ref' => 'Quotation number',
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

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user')->withDefault();
    }

    public function amountLabel(): string
    {
        return Finance::ksh((float) $this->amount_ksh);
    }

    public function label(): string
    {
        return ($this->supplier->name ?? 'Supplier').' — '.$this->amountLabel();
    }
}
