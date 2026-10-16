<?php

namespace App\Models;

use App\Support\Construction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 8 §11.2 — one line of the bill of quantities. The total of a design is never typed: it is the sum of these
 * lines, so a BoQ that has been priced can be checked line by line by anyone reading it.
 */
class BoqItem extends Model
{
    public const SOURCES = ['design' => 'From the design', 'variation' => 'Brought in by a variation'];

    protected $fillable = [
        'project_id', 'drawing_ref', 'trade', 'description', 'unit', 'quantity',
        'unit_cost_ksh', 'position', 'source', 'variation_order_id',
        'status', 'withdraw_note', 'withdrawn_by_user',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'unit_cost_ksh' => 'decimal:2', 'position' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function withdrawer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_user')->withDefault();
    }

    /** A line that came out of an approved variation was agreed with the hirer — it is not re-priced here. */
    public function isLocked(): bool
    {
        return $this->source === 'variation';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(VariationOrder::class, 'variation_order_id')->withDefault();
    }

    public function totalKsh(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost_ksh, 2);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function tradeLabel(): string
    {
        return Construction::SPECIALTIES[$this->trade] ?? (string) $this->trade;
    }

    /** The repeater on the office screen: one row per line, priced or unpriced. */
    public static function rowsFrom($request): array
    {
        $rows = [];

        foreach ((array) $request->input('boq', []) as $i => $row) {
            if (blank($row['description'] ?? null)) {
                continue;
            }

            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'position' => (int) $i,
                'drawing_ref' => str($row['drawing_ref'] ?? '')->limit(80, '')->toString(),
                'trade' => array_key_exists($row['trade'] ?? '', Construction::SPECIALTIES) ? $row['trade'] : null,
                'description' => str($row['description'])->limit(500, '')->toString(),
                'unit' => str($row['unit'] ?? 'each')->limit(30, '')->toString(),
                'quantity' => (float) ($row['quantity'] ?? 0),
                'unit_cost_ksh' => (float) ($row['unit_cost_ksh'] ?? 0),
            ];
        }

        return $rows;
    }
}
