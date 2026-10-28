<?php

namespace App\Models;

use App\Support\Technology;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 9 ,  — a repair or fault ticket on an asset. Opened in the field or at the office, assigned to a
 * fundi, answered with the fix and closed. A ticket that is still open is what drives the department badge.
 */
class TechnologyTicket extends Model
{
    protected $table = 'technology_tickets';

    protected $fillable = [
        'reference', 'asset_id', 'title', 'fault', 'opened_by', 'opened_by_type',
        'assigned_fundi_id', 'priority', 'status', 'resolution', 'resolved_on', 'closed_on', 'created_by_user',
    ];

    protected $casts = ['resolved_on' => 'datetime', 'closed_on' => 'datetime'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(TechnologyAsset::class);
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'assigned_fundi_id')->withDefault();
    }

    public function statusLabel(): string
    {
        return Technology::TICKET_STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function priorityLabel(): string
    {
        return ucfirst((string) $this->priority);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'assigned', 'in_progress'], true);
    }

    public static function nextReference(): string
    {
        $prefix = 'TTK-'.now()->year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
