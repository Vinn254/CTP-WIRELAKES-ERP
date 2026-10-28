<?php

namespace App\Models;

use App\Support\Departments;
use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * A requisition: a desk inside the organisation asking for something it needs to do its work. Any department raises
 * one, Procurement checks it against what the programme already has and approves or rejects it, and Finance
 * processes it — paying out (Disbursed) or releasing goods from the store (Distributed). A row is never deleted: a
 * rejection or a correction stays on the register with the note that explains it.
 */
class Requisition extends Model
{
    public const STATUSES = [
        'submitted' => 'Raised — waiting for Procurement',
        'approved' => 'Approved — with Finance to process',
        'rejected' => 'Rejected — with the reason',
        'disbursed' => 'Disbursed — the money was paid out',
        'distributed' => 'Distributed — the goods were handed over',
    ];

    /** The three steps, in the order the register walks them. */
    public const PATH = [
        ['status' => 'submitted', 'actor' => 'Any department desk', 'action' => 'Fills the requisition — the item, how much, the quality wanted and an estimated price'],
        ['status' => 'approved', 'actor' => 'Procurement Officer', 'action' => 'Checks it against stock and the budget, then approves it or rejects it with a reason'],
        ['status' => 'disbursed', 'actor' => 'Finance Officer', 'action' => 'Processes it: pays the money out and posts a line on the money register, or releases the goods and writes who received them'],
    ];

    /** How the money was actually moved when a requisition is disbursed. */
    public const PAYMENTS = [
        'mpesa' => 'M-Pesa',
        'bank' => 'Bank transfer',
        'cheque' => 'Cheque',
        'cash' => 'Cash at the desk',
    ];

    protected $fillable = [
        'reference', 'department', 'item_name', 'quantity', 'unit', 'quality_note', 'estimated_price_ksh',
        'budget_category', 'purpose', 'needed_by', 'status', 'project_id', 'raised_by_user',
        'decided_by_user', 'decided_at', 'decision_note', 'processed_by_user', 'processed_at',
        'paid_amount_ksh', 'paid_method', 'given_to', 'finance_note',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'estimated_price_ksh' => 'decimal:2',
        'paid_amount_ksh' => 'decimal:2',
        'needed_by' => 'date',
        'decided_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /** One validator — every desk fills the same form. */
    public static function dataFrom(Request $request): array
    {
        $departs = Departments::visibleSlugs($request->user());

        return $request->validate([
            'department' => 'required|in:'.implode(',', $departs),
            'item_name' => 'required|string|max:190',
            'quantity' => 'required|numeric|min:0.01|max:999999',
            'unit' => 'required|in:'.implode(',', array_keys(InventoryItem::UNITS)),
            'quality_note' => 'nullable|string|max:1000',
            'estimated_price_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'budget_category' => 'nullable|in:'.implode(',', array_keys(BudgetLine::categories())),
            'purpose' => 'required|string|max:1000|min:15',
            'needed_by' => 'nullable|date',
            'project_id' => 'nullable|integer|exists:projects,id',
        ], [
            'purpose.min' => 'Say what the desk needs it for — Procurement decides on that line, not the item name.',
            'quantity.min' => 'A requisition states how much is wanted.',
        ], [
            'department' => 'Which desk is asking',
            'item_name' => 'What is wanted',
            'quantity' => 'Quantity',
            'quality_note' => 'Quality or standard',
            'estimated_price_ksh' => 'Estimated price',
            'purpose' => 'What it is for',
        ]);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user')->withDefault();
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user')->withDefault();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withDefault();
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("case status when 'submitted' then 0 when 'approved' then 1 when 'disbursed'
            then 2 when 'distributed' then 3 else 4 end")->orderByDesc('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** The short form a register table has room for; the full label reads on the row's own page. */
    public function shortLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'With Procurement',
            'approved' => 'With Finance',
            'rejected' => 'Rejected',
            'disbursed' => 'Disbursed',
            'distributed' => 'Distributed',
            default => ucfirst($this->status),
        };
    }

    public function departmentLabel(): string
    {
        return Departments::find($this->department)['name'] ?? $this->department;
    }

    public function quantityLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.').' '.InventoryItem::unitLabel($this->unit);
    }

    public function priceLabel(): string
    {
        return $this->estimated_price_ksh !== null
            ? Finance::ksh((float) $this->estimated_price_ksh)
            : 'price not estimated';
    }

    public function paidLabel(): string
    {
        return $this->paid_amount_ksh !== null ? Finance::ksh((float) $this->paid_amount_ksh) : '—';
    }

    public function budgetLabel(): string
    {
        return BudgetLine::categoryLabel($this->budget_category);
    }

    /** Where the row sits on the three-step path. */
    public function stepIndex(): int
    {
        return match ($this->status) {
            'submitted' => 0,
            'approved' => 1,
            'disbursed', 'distributed' => 2,
            default => 0,
        };
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['rejected', 'disbursed', 'distributed'], true);
    }

    /** A row only moves forward from the step it is actually on. */
    public function mustBe(string ...$statuses): void
    {
        if (in_array($this->status, $statuses, true)) {
            return;
        }

        $wanted = collect($statuses)->map(fn ($s) => self::STATUSES[$s] ?? $s)->join(' or ');

        abort(422, $this->reference.' is on step '.($this->stepIndex() + 1).' of the requisition path ('
            .$this->statusLabel().'), so that action is not open on it yet. It has to be: '.$wanted.'.');
    }

    /** RQ-2026-00001 — the number the desk quotes when it follows the request up. */
    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'RQ-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
