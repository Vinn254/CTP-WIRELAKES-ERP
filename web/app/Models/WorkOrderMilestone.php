<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 7 — one milestone on a work order (§10.2, §10.5). A percentage of the agreed amount that is only
 * released when a supervisor or manager has signed that stage off (§10.4 step 4). The share is stored rather than
 * derived, so a custom split the office chose stays readable after the amounts change.
 */
class WorkOrderMilestone extends Model
{
    public const PENDING = 'pending';
    public const SIGNED = 'signed';
    public const DROPPED = 'dropped';

    public const STATUSES = [
        self::PENDING => 'Not signed off yet',
        self::SIGNED => 'Signed off',
        self::DROPPED => 'Off the split — the office changed the schedule',
    ];

    protected $fillable = [
        'work_order_id', 'name', 'share_percent', 'amount_ksh', 'due_on', 'status',
        'signed_on', 'signed_by_user', 'note',
    ];

    protected $casts = [
        'share_percent' => 'integer',
        'amount_ksh' => 'decimal:2',
        'due_on' => 'date',
        'signed_on' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isSigned(): bool
    {
        return $this->status === self::SIGNED;
    }

    /** The money this stage is worth: the figure the office typed, or the share of the agreed amount. */
    public function amountLabel(): string
    {
        return Finance::ksh($this->amount());
    }

    public function amount(): float
    {
        if ($this->amount_ksh !== null) {
            return (float) $this->amount_ksh;
        }

        return round((float) ($this->workOrder?->agreed_amount_ksh ?? 0) * (int) $this->share_percent / 100, 2);
    }
}
