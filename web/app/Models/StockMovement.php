<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 11 — one movement on a stock card. Goods in from a purchase order or a collection point, materials out
 * to a project or a cohort, and a correction after a count. A movement is never edited away: a mistake is
 * corrected with another movement in the opposite direction so both the error and the fix stay visible.
 */
class StockMovement extends Model
{
    public const IN_REASONS = [
        'purchase_order' => 'Goods received on a purchase order',
        'e_waste_intake' => 'E-waste brought to a collection point',
        'return' => 'Returned to the store',
        'transfer_in' => 'Moved in from another store',
        'opening' => 'Opening balance on a new card',
    ];

    public const OUT_REASONS = [
        'project_issue' => 'Materials issued to a project',
        'training_issue' => 'Materials issued to a cohort',
        'disposal' => 'Disposed of',
        'transfer_out' => 'Moved to another store',
    ];

    public const REASONS = self::IN_REASONS + self::OUT_REASONS;

    protected $fillable = [
        'inventory_item_id', 'direction', 'quantity', 'unit_cost_ksh', 'reason', 'note',
        'project_id', 'cohort_id', 'purchase_order_id', 'source_type', 'source_id',
        'moved_on', 'recorded_by_user',
    ];

    protected $casts = ['quantity' => 'decimal:2', 'unit_cost_ksh' => 'decimal:2', 'moved_on' => 'date'];

    /** The reason list for one direction, so a form can only offer the moves that make sense. */
    public static function reasonsFor(string $direction): array
    {
        return $direction === 'in' ? self::IN_REASONS : self::OUT_REASONS;
    }

    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'direction' => 'required|in:in,out',
            'reason' => 'required|string',
            'quantity' => 'required|numeric|min:0.01|max:1000000',
            'unit_cost_ksh' => 'nullable|numeric|min:0|max:100000000',
            'moved_on' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'project_id' => 'nullable|integer|exists:projects,id',
            'cohort_id' => 'nullable|integer|exists:cohorts,id',
        ], [
            'reason.required' => 'Say why the shelf changed — that is what the store is reconciled against.',
        ], [
            'reason' => 'Why',
            'quantity' => 'How much',
            'unit_cost_ksh' => 'Unit cost',
            'moved_on' => 'Date',
        ]);

        abort_unless(isset(self::reasonsFor($data['direction'])[$data['reason']]), 422,
            '"'.$data['reason'].'" is not a movement that goes '.$data['direction']
            .'. Pick a reason from that direction\'s list.');

        return $data;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withDefault();
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class)->withDefault();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withDefault();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user')->withDefault();
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucwords(str_replace('_', ' ', $this->reason));
    }

    public function quantityLabel(): string
    {
        $n = rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.');

        return ($this->direction === 'in' ? '+' : '−').$n.' '.($this->item->unit ?? 'pcs');
    }

    public function valueLabel(): string
    {
        return Finance::ksh((float) $this->quantity * (float) ($this->unit_cost_ksh ?? $this->item->unit_cost_ksh ?? 0));
    }

    public function forLabel(): string
    {
        if ($this->project->exists) {
            return 'Project '.$this->project->title;
        }

        if ($this->cohort->exists) {
            return 'Cohort '.$this->cohort->reference;
        }

        if ($this->order->exists) {
            return 'Against '.$this->order->reference;
        }

        return $this->note ?: 'The store itself';
    }
}
