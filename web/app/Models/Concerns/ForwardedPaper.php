<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The life of a piece of teaching paperwork: the trainer fills it in, forwards it to the Academic Registrar, and the
 * registrar either approves it or sends it back with what to fix. Only an approved paper counts on a trainee.
 *
 * Used by the attendance register, a unit's mark sheet, a timetable and a lesson plan — the four papers share one
 * machine so a trainer never learns four different ways to hand work in.
 */
trait ForwardedPaper
{
    public const STATUSES = [
        'draft' => 'Draft — still being filled in',
        'submitted' => 'With the Registrar',
        'approved' => 'Approved',
        'returned' => 'Sent back to fix',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeAwaiting(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status ?? 'draft'] ?? Str::headline((string) $this->status);
    }

    /** A paper is the trainer's to change until it is sitting with the registrar or already approved. */
    public function isEditable(): bool
    {
        return in_array($this->status ?? 'draft', ['draft', 'returned'], true);
    }

    /** The class the status pill wears, matching the one every other office screen uses. */
    public function statusClass(): string
    {
        return match ($this->status ?? 'draft') {
            'approved' => 'active',
            'returned' => 'rejected',
            'submitted' => 'pending_review',
            default => 'draft',
        };
    }
}
