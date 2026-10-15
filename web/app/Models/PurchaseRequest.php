<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Module 11 — a purchase request (§14.4). This is the row the whole procurement path hangs on: anyone (a CTP in
 * the field, an E-Fundi, a manager) raises it, the Programme Manager decides, quotations are attached, a purchase
 * order goes out, goods are received, the invoice is matched and the supplier is paid. A request is never deleted
 * or re-raised — a rejected one stays on the register with its reason.
 */
class PurchaseRequest extends Model
{
    public const STATUSES = [
        'submitted' => 'Raised — waiting for a decision',
        'approved' => 'Approved — needs quotations',
        'rejected' => 'Rejected',
        'quoted' => 'Quotations in — waiting for a purchase order',
        'ordered' => 'Purchase order sent to the supplier',
        'received' => 'Goods received at the store',
        'matched' => 'Matched — payment requested',
        'paid' => 'Supplier paid and recorded',
    ];

    /** The eight steps of §14.4, in the order the register walks them. */
    public const PATH = [
        ['status' => 'submitted', 'actor' => 'Anyone — a CTP, an E-Fundi or a manager', 'action' => 'Raises the purchase request with item, quantity, justification and the project to charge'],
        ['status' => 'approved', 'actor' => 'Programme Manager', 'action' => 'Reviews and approves it — or rejects it with a reason'],
        ['status' => 'quoted', 'actor' => 'Procurement Officer', 'action' => 'Gets quotations from approved suppliers'],
        ['status' => 'ordered', 'actor' => 'Procurement Officer', 'action' => 'Raises a purchase order and sends it to the supplier'],
        ['status' => 'received', 'actor' => 'Storekeeper', 'action' => 'Receives the goods, checks quantity and quality, signs the delivery note and updates inventory'],
        ['status' => 'matched', 'actor' => 'Procurement Officer', 'action' => 'Matches the PO, the delivery note and the supplier invoice, and raises the payment request'],
        ['status' => 'paid', 'actor' => 'Finance Officer', 'action' => 'Pays the supplier (M-Pesa, bank or cheque) and records the payment'],
    ];

    protected $fillable = [
        'reference', 'item_name', 'description', 'quantity', 'unit', 'estimated_cost_ksh', 'budget_category',
        'justification', 'needed_by', 'county', 'project_id', 'cohort_id', 'inventory_item_id',
        'raised_by_user', 'ctp_id', 'fundi_id', 'status', 'decided_by_user', 'decided_on', 'decision_note',
        'rejection_reason',
    ];

    protected $casts = [
        'estimated_cost_ksh' => 'decimal:2',
        'needed_by' => 'date',
        'decided_on' => 'datetime',
    ];

    /** One validator — the office, the CTP portal and the E-Fundi portal all raise a request the same way. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'item_name' => 'required|string|max:190',
            'description' => 'nullable|string|max:1000',
            'quantity' => 'required|integer|min:1|max:1000000',
            'unit' => 'required|in:'.implode(',', array_keys(InventoryItem::UNITS)),
            'estimated_cost_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'budget_category' => 'required|in:'.implode(',', array_keys(BudgetLine::CATEGORIES)),
            'justification' => 'required|string|max:1000|min:15',
            'needed_by' => 'nullable|date',
            'county' => 'nullable|string|max:100',
            'project_id' => 'nullable|integer|exists:projects,id',
            'cohort_id' => 'nullable|integer|exists:cohorts,id',
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
        ], [
            'justification.min' => 'Say why the programme needs this — a decision is made on the justification, not the item name.',
            'budget_category.required' => 'Every request is charged to a budget line (§14.4 step 1).',
        ], [
            'item_name' => 'What is needed',
            'estimated_cost_ksh' => 'What it will cost',
            'budget_category' => 'Charge to budget',
            'needed_by' => 'Needed by',
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withDefault();
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class)->withDefault();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withDefault();
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    public function ctp(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'ctp_id')->withDefault();
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id')->withDefault();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user')->withDefault();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(PurchaseQuotation::class)->orderBy('amount_ksh');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("case status when 'submitted' then 0 when 'approved' then 1 when 'quoted' then 2
            when 'ordered' then 3 when 'received' then 4 when 'matched' then 5 else 6 end")
            ->orderByDesc('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function budgetLabel(): string
    {
        return BudgetLine::categoryLabel($this->budget_category);
    }

    public function quantityLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.').' '.$this->unit;
    }

    public function costLabel(): string
    {
        return $this->estimated_cost_ksh !== null ? Finance::ksh((float) $this->estimated_cost_ksh) : 'cost not estimated';
    }

    /** Who raised it — a staff member, a CTP in the field or an E-Fundi. */
    public function raiserLabel(): string
    {
        if ($this->ctp_id && $this->ctp->exists) {
            return $this->ctp->full_name.' ('.($this->ctp->ctp_code ?: 'CTP').')';
        }

        if ($this->fundi_id && $this->fundi->exists) {
            return $this->fundi->full_name.' ('.($this->fundi->ef_code ?: 'E-Fundi').')';
        }

        return $this->raiser->exists ? $this->raiser->name : 'Raised in the office';
    }

    public function chargeLabel(): string
    {
        return collect([
            $this->project->exists ? $this->project->title : null,
            $this->cohort->exists ? $this->cohort->reference : null,
        ])->filter()->join(' · ') ?: 'General programme need';
    }

    /** Where this row sits on the §14.4 path, for the eight-step panel. */
    public function stepIndex(): int
    {
        $order = array_keys(self::STATUSES);
        $position = array_search($this->status, $order, true);

        return $position === false ? 0 : (int) $position;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['paid', 'rejected'], true);
    }

    /** A row only moves forward from the step it is actually on — no jumping to a payment. */
    public function mustBe(string ...$statuses): void
    {
        if (in_array($this->status, $statuses, true)) {
            return;
        }

        $wanted = collect($statuses)->map(fn ($s) => self::STATUSES[$s] ?? $s)->join(' or ');

        abort(422, $this->reference.' is on step '.($this->stepIndex() + 1).' of the procurement path ('
            .$this->statusLabel().'), so that action is not open on it yet. It has to be: '.$wanted.'.');
    }

    /** PR-2026-00004 — the number the delivery note and the invoice are quoted with. */
    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'PR-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
