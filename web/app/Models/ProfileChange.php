<?php

namespace App\Models;

use App\Support\Profile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An edit a person asks the office to make to their own profile. The fields they want changed are kept here as the
 * ask, never written onto the record: only when the desk approves does the answer land on the staff file, CTP
 * application or E-Fundi registration. A declined request stays on the trail with the note that declined it.
 */
class ProfileChange extends Model
{
    public const STATUSES = Profile::CHANGE_STATUSES;

    // The desk's answer is written by decide() with forceFill(), never from a posted array.
    protected $fillable = ['subject_type', 'subject_id', 'changes', 'note', 'requested_by_user'];

    protected $casts = ['changes' => 'array', 'decided_at' => 'datetime'];

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user');
    }

    public function subject(): ?Model
    {
        $class = Profile::SUBJECTS[$this->subject_type] ?? null;

        return $class ? $class::find($this->subject_id) : null;
    }

    public function personLabel(): string
    {
        return Profile::person($this->subject_type, $this->subject());
    }

    public function officeLabel(): string
    {
        return Profile::office($this->subject_type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Each field asked for, with what is on file now beside it — the whole card reads from this. */
    public function lines(): array
    {
        return Profile::compare($this->subject_type, $this->subject(), $this->changes ?? []);
    }

    public static function forSubject(string $type, int $id): Collection
    {
        return static::with(['asker:id,name', 'decidedBy:id,name'])
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->orderByDesc('id')
            ->get();
    }

    /** The one ask still waiting, if the person has one — a second would contradict the first. */
    public static function pendingFor(string $type, int $id): ?self
    {
        return static::where('subject_type', $type)->where('subject_id', $id)
            ->where('status', 'pending')->latest('id')->first();
    }

    public static function waiting(array $types): Collection
    {
        return static::with(['asker:id,name'])
            ->whereIn('subject_type', $types)
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();
    }

    public static function waitingCount(array $types): int
    {
        return static::whereIn('subject_type', $types)->where('status', 'pending')->count();
    }
}
