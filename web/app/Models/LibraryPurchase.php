<?php

namespace App\Models;

use App\Support\Library;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One title a student asked to keep, and what the office said about the money. The student's own row is written from
 * their library page; the status, the note and the signature belong to the desk and are never filled from a request
 * array. Asking again after a decline writes the same row over rather than adding a second one.
 */
class LibraryPurchase extends Model
{
    public const STATUSES = Library::PURCHASES;

    protected $fillable = ['ctp_id', 'library_item_id', 'amount', 'requested_by_user'];

    protected $casts = ['amount' => 'integer', 'decided_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'ctp_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user');
    }

    public function personLabel(): string
    {
        return $this->student?->full_name ?: 'A student no longer on file';
    }

    public function titleLabel(): string
    {
        return $this->item?->title ?: 'A title no longer on the shelf';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isWaiting(): bool
    {
        return $this->status === 'requested';
    }

    public function dateLabel(): string
    {
        return ($this->decided_at ?? $this->created_at)?->format('d M Y') ?: 'today';
    }
}
