<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One flagged need in a community — the line the programme manager prioritises and can turn into a project. */
class Need extends Model
{
    /** The checklist in §6.4, widened to the need families the programme actually funds. */
    public const TYPES = [
        'ict' => 'ICT equipment (computers, printers)',
        'internet' => 'Internet connectivity',
        'solar' => 'Solar power',
        'e_waste' => 'E-waste collection point',
        'robotics' => 'Robotics / STEM training',
        'skills_gap' => 'Skills gap',
        'infrastructure' => 'Infrastructure',
        'youth_opportunity' => 'Youth opportunity',
        'business_opportunity' => 'Business opportunity',
        'existing_asset' => 'Existing asset already in place',
    ];

    public const STATUSES = [
        'proposed' => 'Proposed by CTP',
        'prioritised' => 'Prioritised',
        'converted' => 'Converted to project',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'standard' => 'Standard',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    protected $fillable = [
        'community_id', 'type', 'title', 'detail', 'priority', 'status',
        'photo_path', 'gps_lat', 'gps_lng', 'assessed_by_ctp',
        'reviewed_by', 'reviewed_at', 'project_id', 'converted_at',
    ];

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'reviewed_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'assessed_by_ctp');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function typeLabel(string $key): string
    {
        return self::TYPES[$key] ?? Str::headline($key);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? Str::headline($this->priority);
    }

    /** Needs that count toward the programme pipeline — staff have stood behind them. */
    public function isLive(): bool
    {
        return in_array($this->status, ['proposed', 'prioritised'], true);
    }

    public function hasPin(): bool
    {
        return (bool) $this->gps_lat && (bool) $this->gps_lng;
    }
}
