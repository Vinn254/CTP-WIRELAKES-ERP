<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Module 11 — a stock card. SKU, description, unit, quantity on hand, reorder level, where it sits and
 * what it cost. The quantity is never typed in: it is the sum of the movements on the card, so the store can
 * always be explained back to a purchase order, a project that drew materials or a count.
 */
class InventoryItem extends Model
{
    public const CATEGORIES = [
        'technology' => 'Technology',
        'construction' => 'Construction',
        'ewaste' => 'E-waste',
        'office_training' => 'Office and training',
    ];

    public const UNITS = [
        'pcs' => 'Pieces',
        'set' => 'Sets',
        'kg' => 'Kilograms',
        'bag' => 'Bags',
        'm' => 'Metres',
        'roll' => 'Rolls',
        'l' => 'Litres',
        'box' => 'Boxes',
        'carton' => 'Cartons',
        'ream' => 'Reams of paper',
    ];

    /** The short code a SKU starts with — the shelf label and the register have to agree. */
    public const SKU_PREFIX = [
        'technology' => 'TEC',
        'construction' => 'CST',
        'ewaste' => 'EWS',
        'office_training' => 'OFF',
    ];

    protected $fillable = [
        'sku', 'name', 'description', 'category', 'unit', 'qty_on_hand', 'reorder_level',
        'location', 'unit_cost_ksh', 'status',
    ];

    protected $casts = [
        'qty_on_hand' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'unit_cost_ksh' => 'decimal:2',
    ];

    public static function categoryLabel(?string $key): string
    {
        if (! $key) {
            return 'Unclassified';
        }

        return self::CATEGORIES[$key] ?? ucwords(str_replace('-', ' ', $key));
    }

    public static function unitLabel(string $key): string
    {
        return self::UNITS[$key] ?? $key;
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:190',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:'.implode(',', array_keys(self::CATEGORIES)),
            'unit' => 'required|in:'.implode(',', array_keys(self::UNITS)),
            'reorder_level' => 'required|numeric|min:0|max:1000000',
            'location' => 'required|string|max:190',
            'unit_cost_ksh' => 'required|numeric|min:0|max:100000000',
        ], [
            'location.required' => 'Say which store or which project the item sits at — a stock card without a place cannot be found.',
            'reorder_level.required' => 'Set the level the store reorders at; that is what raises the flag on this card.',
        ], [
            'name' => 'Item',
            'reorder_level' => 'Reorder level',
            'unit_cost_ksh' => 'Unit cost',
        ]);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'inventory_item_id');
    }

    public function statusLabel(): string
    {
        return $this->status === 'active' ? 'On the stock register' : 'Off the register';
    }

    public function quantityLabel(): string
    {
        return $this->numberLabel($this->qty_on_hand);
    }

    public function reorderLabel(): string
    {
        return $this->numberLabel($this->reorder_level);
    }

    public function numberLabel($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').' '.static::unitLabel((string) $this->unit);
    }

    public function unitCostLabel(): string
    {
        return Finance::ksh((float) $this->unit_cost_ksh);
    }

    public function valueLabel(): string
    {
        return Finance::ksh((float) $this->qty_on_hand * (float) $this->unit_cost_ksh);
    }

    public function placeLabel(): string
    {
        return $this->location ?: 'Store not recorded';
    }

    public function isLow(): bool
    {
        return (float) $this->reorder_level > 0 && (float) $this->qty_on_hand <= (float) $this->reorder_level;
    }

    public function isEmpty(): bool
    {
        return (float) $this->qty_on_hand <= 0;
    }

    /**
     * The only way a card changes. An issue cannot take the shelf below zero — the store either has the material
     * or a purchase request has to bring it in first.
     */
    public function applyMovement(string $direction, float $quantity): void
    {
        $next = $direction === 'in'
            ? (float) $this->qty_on_hand + $quantity
            : (float) $this->qty_on_hand - $quantity;

        abort_if($next < 0, 422,
            $this->name.' has only '.$this->quantityLabel().' on the shelf at '.$this->placeLabel()
            .'. You cannot issue '.rtrim(rtrim(number_format($quantity, 2), '0'), '.').' '.$this->unit
            .' — raise a purchase request for the difference instead.');

        $this->update(['qty_on_hand' => $next]);
    }

    /** INV-TEC-0007 — the SKU a delivery note is written against. */
    public static function nextSku(string $category): string
    {
        $prefix = 'INV-'.(self::SKU_PREFIX[$category] ?? 'GEN').'-';

        $highest = static::where('sku', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(sku, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /** The register totals shown above every stock screen. */
    public static function totals(): array
    {
        $items = static::where('status', 'active')->get();

        return [
            'cards' => $items->count(),
            'value' => (float) $items->sum(fn ($i) => (float) $i->qty_on_hand * (float) $i->unit_cost_ksh),
            'low' => $items->filter(fn ($i) => $i->isLow() && ! $i->isEmpty())->count(),
            'empty' => $items->filter(fn ($i) => $i->isEmpty())->count(),
        ];
    }
}
