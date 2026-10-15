<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Module 11 — an approved supplier (§14.4 step 3). Quotations only come from the suppliers on this list, and a
 * supplier that stops supplying is taken off the list rather than deleted: the purchase orders they already
 * delivered stay readable.
 */
class Supplier extends Model
{
    public const STATUSES = [
        'approved' => 'Approved to quote',
        'inactive' => 'Off the approved list',
    ];

    protected $fillable = [
        'code', 'name', 'contact_name', 'phone', 'email', 'county', 'supplies',
        'bank_account', 'mpesa_paybill', 'status', 'note', 'added_by_user',
    ];

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:190',
            'contact_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:190',
            'county' => 'nullable|string|max:100',
            'supplies' => 'nullable|in:'.implode(',', array_keys(InventoryItem::CATEGORIES)),
            'bank_account' => 'nullable|string|max:120',
            'mpesa_paybill' => 'nullable|string|max:60',
            'note' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'A supplier is paid against paperwork, so they need a real name on the list.',
        ], [
            'supplies' => 'What they supply',
            'bank_account' => 'Bank account',
            'mpesa_paybill' => 'M-Pesa paybill',
        ]);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(PurchaseQuotation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function adder()
    {
        return $this->belongsTo(User::class, 'added_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function suppliesLabel(): string
    {
        return $this->supplies ? InventoryItem::categoryLabel($this->supplies) : 'Any goods';
    }

    public function contactLabel(): string
    {
        return collect([$this->contact_name, $this->phone, $this->email])->filter()->join(' · ') ?: 'No contact recorded';
    }

    public function payLabel(): string
    {
        return collect([$this->mpesa_paybill ? 'M-Pesa '.$this->mpesa_paybill : null,
            $this->bank_account ? 'Bank '.$this->bank_account : null])->filter()->join(' · ') ?: 'Payment details not recorded';
    }

    /** SUP-0001 — the supplier number the delivery notes and invoices are filed under. */
    public static function nextCode(): string
    {
        $highest = static::where('code', 'like', 'SUP-%')
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) n')->value('n');

        return 'SUP-'.str_pad((string) ((int) $highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
