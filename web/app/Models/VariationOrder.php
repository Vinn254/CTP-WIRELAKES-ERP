<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 8  — work the design did not carry. A Construction Manager raises it with its cost; a Director approves
 * or rejects it; an approved order is what moves the work order's agreed amount and adds its lines to the BoQ. A
 * rejected order stays on the file, because "we asked and it was refused" is part of the record.
 */
class VariationOrder extends Model
{
    public const STATUSES = [
        'proposed' => 'Raised — waiting for a Director',
        'approved' => 'Approved — the order and the BoQ have moved',
        'rejected' => 'Rejected — the design stands as agreed',
    ];

    protected $fillable = [
        'reference', 'project_id', 'work_order_id', 'scope', 'reason', 'extra_cost_ksh',
        'extra_materials', 'extra_days', 'status', 'raised_by_user',
        'decided_by_user', 'decided_on', 'decision_note',
    ];

    protected $casts = ['decided_on' => 'datetime', 'extra_cost_ksh' => 'decimal:2', 'extra_days' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withDefault();
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user')->withDefault();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'VAR-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'scope' => 'required|string|max:5000',
            'reason' => 'required|string|max:1000',
            'extra_cost_ksh' => 'required|numeric|min:0|max:1000000000',
            'extra_materials' => 'nullable|string|max:1000',
            'extra_days' => 'nullable|integer|min:0|max:3650',
        ], [
            'scope.required' => 'Write the additional work out — a variation with no scope cannot be priced or approved.',
            'reason.required' => 'Say why the design has to change: what was found on site, or what the hirer asked for.',
            'extra_cost_ksh.required' => 'Give the additional cost. An approved variation always carries a figure.',
        ], [
            'scope' => 'The additional work',
            'reason' => 'Why it is needed',
            'extra_cost_ksh' => 'Additional cost (KSh)',
            'extra_materials' => 'Materials the change needs',
            'extra_days' => 'Extra days on the programme',
        ]);
    }
}
