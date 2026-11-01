<?php

namespace App\Models;

use App\Support\Profile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One paper a person sends in for the desk to check — a scan of an ID, a KRA certificate, a degree, a photo. The
 * upload and its kind are the person's own; the status, the note and the signature belong to the desk and are
 * never filled from a request array. Sending a better copy writes over the one that was sent back.
 */
class MemberDocument extends Model
{
    public const STATUSES = Profile::STATUSES;

    protected $fillable = [
        'subject_type', 'subject_id', 'kind', 'file_path', 'file_name', 'file_size', 'uploaded_by_user',
    ];

    protected $casts = ['decided_at' => 'datetime', 'file_size' => 'integer'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user');
    }

    /** The staff file, CTP application or E-Fundi registration this paper belongs to. */
    public function subject(): ?Model
    {
        $class = Profile::SUBJECTS[$this->subject_type] ?? null;

        return $class ? $class::find($this->subject_id) : null;
    }

    public function personLabel(): string
    {
        return Profile::person($this->subject_type, $this->subject());
    }

    public function kindLabel(): string
    {
        return Profile::paperLabel($this->subject_type, $this->kind);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function officeLabel(): string
    {
        return Profile::office($this->subject_type);
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && Storage::disk('local')->exists($this->file_path);
    }

    public function isWaiting(): bool
    {
        return $this->status === 'pending';
    }

    /** Can this copy still be sent again? An approved paper is settled and stays as it is. */
    public function replaceable(): bool
    {
        return in_array($this->status, ['pending', 'returned'], true);
    }

    public function sizeLabel(): string
    {
        return $this->file_size ? number_format($this->file_size / 1024, 0).' KB' : '—';
    }

    /** Every paper on one record, newest first — the person's own page and the desk both read this. */
    public static function forSubject(string $type, int $id): Collection
    {
        return static::with(['uploader:id,name', 'decidedBy:id,name'])
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->orderByDesc('id')
            ->get();
    }

    /** The papers waiting on one desk, newest first. */
    public static function waiting(array $types): Collection
    {
        return static::with(['uploader:id,name'])
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
