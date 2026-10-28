<?php

namespace App\Models;

use App\Support\Leave;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One leave application. The dates and the day count are what the person asked for; the decision, its note and its
 * signature are only ever written by the Human Resources desk, which is why the status columns are not fillable.
 */
class LeaveRequest extends Model
{
    public const STATUSES = Leave::STATUSES;

    // Status, decision note and signature are never filled from a request array — only the desk's decide() writes
    // them, with forceFill(). A new application is pending by the column's own default.
    protected $fillable = ['user_id', 'type', 'starts_on', 'ends_on', 'days', 'cover_for', 'phone', 'reason'];
    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'days' => 'float',
        'decided_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user');
    }

    public function typeLabel(): string
    {
        return Leave::name($this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function periodLabel(): string
    {
        return $this->starts_on->format('d M Y').' – '.$this->ends_on->format('d M Y');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Who is signed in on this account, as the register lists them: an office name or an E-Fundi. */
    public function workerLabel(): string
    {
        return $this->user?->name ?? 'Removed account';
    }

    public function holderKind(): string
    {
        return match ($this->user?->role) {
            'superadmin' => 'Super admin',
            'admin' => 'Office staff',
            'fundi' => 'E-Fundi',
            default => 'Member',
        };
    }

    /** Applications of one person, newest first — their own page and the desk both read this. */
    public static function forUser(int $userId, ?int $year = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['user:id,name,role', 'decidedBy:id,name'])
            ->where('user_id', $userId)
            ->when($year, fn ($q) => $q->whereYear('starts_on', $year))
            ->orderByDesc('created_at')
            ->get();
    }
}
