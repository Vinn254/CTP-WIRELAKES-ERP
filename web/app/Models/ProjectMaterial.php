<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 11 / Projects — one material the programme provides for a project (§14.5, §14.6 step 6).
 * The office lists these when it posts the project so the fundi knows what they are given and what it is
 * worth; the lines actually handed over are the figure the payout deducts. A line is never deleted — a material
 * that turns out not to be needed simply stays un-supplied, so the plan stays visible beside the money.
 */
class ProjectMaterial extends Model
{
    protected $fillable = [
        'project_id', 'inventory_item_id', 'name', 'quantity', 'unit', 'unit_cost_ksh',
        'supplied_on', 'supplied_by_user', 'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost_ksh' => 'decimal:2',
        'supplied_on' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The stock card it came from, when the store issued it rather than the market. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withDefault();
    }

    /** Who handed it over — the storekeeper or the site supervisor, not the supplier. */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplied_by_user')->withDefault();
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'name' => 'nullable|string|max:190',
            'quantity' => 'required|numeric|min:0.01|max:1000000',
            'unit' => 'nullable|in:'.implode(',', array_keys(InventoryItem::UNITS)),
            'unit_cost_ksh' => 'nullable|numeric|min:0|max:100000000',
            'note' => 'nullable|string|max:1000',
        ], [
            'quantity.required' => 'Say how much the job needs; the deduction is worked out from the quantity and the unit cost.',
            'quantity.min' => 'A material line is always for some quantity above zero.',
        ], [
            'inventory_item_id' => 'Stock card',
            'name' => 'Material',
            'quantity' => 'Quantity',
            'unit_cost_ksh' => 'Cost each',
        ]);
    }

    public function totalKsh(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost_ksh, 2);
    }

    public function quantityLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.').' '
            .InventoryItem::unitLabel((string) $this->unit);
    }

    public function unitCostLabel(): string
    {
        return Finance::ksh((float) $this->unit_cost_ksh);
    }

    public function totalLabel(): string
    {
        return Finance::ksh($this->totalKsh());
    }

    public function isSupplied(): bool
    {
        return $this->supplied_on !== null;
    }
}
