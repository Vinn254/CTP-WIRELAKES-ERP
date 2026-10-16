<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 8 §11.4 — a site inspection. It is scheduled first, then reported item by item, then signed off. An item
 * that fails is flagged on the inspection and keeps its own proof and re-check, so a signed-off report still shows
 * what went wrong and what was done about it.
 */
class SiteInspection extends Model
{
    public const STATUSES = [
        'scheduled' => 'Scheduled — not yet visited',
        'reported' => 'Reported — items marked, waiting to be signed off',
        'signed_off' => 'Signed off by the inspector',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'project_id', 'work_order_id', 'scheduled_on', 'inspected_on', 'purpose', 'status',
        'report_note', 'photo_path', 'photo_name', 'photo_size',
        'inspector_user', 'signed_off_on', 'signed_off_by_user',
    ];

    protected $casts = ['scheduled_on' => 'date', 'inspected_on' => 'date', 'signed_off_on' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(SiteInspectionItem::class, 'inspection_id')->orderBy('id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withDefault();
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user')->withDefault();
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_off_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    public function failures(): \Illuminate\Support\Collection
    {
        return $this->items->where('result', 'fail')->values();
    }

    /** §11.4 step 4 — the report is only complete when every item has been marked. */
    public function unmarkedCount(): int
    {
        return (int) $this->items->where('result', 'pending')->count();
    }

    /** Anything that failed and has not been re-checked and cleared yet. */
    public function openFailures(): int
    {
        return (int) $this->items()->where('result', 'fail')->whereNull('rechecked_on')->count();
    }

    public function mustBe(string ...$statuses): void
    {
        if (in_array($this->status, $statuses, true)) {
            return;
        }

        $wanted = collect($statuses)->map(fn ($s) => self::STATUSES[$s] ?? $s)->join(' or ');

        abort(422, 'The inspection scheduled for '.($this->scheduled_on?->format('d M Y') ?? 'a date on the file')
            .' is "'.$this->statusLabel().'", so that step is not open on it yet. It has to be: '.$wanted.'.');
    }
}
