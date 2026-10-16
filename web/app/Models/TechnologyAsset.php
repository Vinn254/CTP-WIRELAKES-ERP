<?php

namespace App\Models;

use App\Support\Technology;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 9 §12 — one technology asset. A device, a licence or a kit, cut by its sub-area (ICT, Networking, Robotics,
 * Programming, AI), carrying its own warranty and licence dates, its assignment to a community or organisation, and
 * the e-waste record it was routed to when it was decommissioned.
 */
class TechnologyAsset extends Model
{
    protected $table = 'technology_assets';

    protected $fillable = [
        'asset_code', 'category', 'name', 'kind', 'description', 'serial_number', 'status', 'qr_payload',
        'assigned_type', 'assigned_id', 'assigned_name', 'location', 'county',
        'purchase_date', 'purchase_cost_ksh', 'supplier',
        'warranty_expires_on', 'licence_key', 'licence_seats', 'licence_renews_on',
        'source', 'ewaste_collection_id', 'decommissioned_on', 'decommission_note', 'notes', 'created_by_user',
    ];

    protected $casts = [
        'purchase_date' => 'date', 'warranty_expires_on' => 'date', 'licence_renews_on' => 'date',
        'decommissioned_on' => 'date', 'purchase_cost_ksh' => 'decimal:2', 'licence_seats' => 'integer',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(TechnologyTicket::class, 'asset_id')->orderByDesc('id');
    }

    public function ewasteCollection(): BelongsTo
    {
        return $this->belongsTo(EWasteCollection::class, 'ewaste_collection_id')->withDefault();
    }

    public function categoriser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return Technology::ASSET_STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function categoryLabel(): string
    {
        return Technology::CATEGORIES[$this->category]['label'] ?? ucfirst((string) $this->category);
    }

    public function sourceLabel(): string
    {
        return Technology::SOURCES[$this->source] ?? ucfirst((string) $this->source);
    }

    public function isInService(): bool
    {
        return $this->status === 'operational';
    }

    public function isBroken(): bool
    {
        return in_array($this->status, ['down', 'degraded', 'in_repair'], true);
    }

    public function isRetired(): bool
    {
        return $this->status === 'retired';
    }

    /** The name a reader recognises: the assignee if it is handed out, else where it sits. */
    public function holderLabel(): string
    {
        return $this->assigned_name ?: ($this->location ?: 'unassigned');
    }

    public static function nextCode(string $category): string
    {
        $prefix = strtoupper(substr($category, 0, 3)).'-'.now()->year.'-';

        $highest = static::where('asset_code', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(asset_code, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
