<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Where training is delivered — a partner school, a community site or a programme centre (§7.3). */
class TrainingVenue extends Model
{
    public const TYPES = [
        'partner_school' => 'Partner school',
        'community_site' => 'Community training site',
        'programme_centre' => 'CTP-WireLakes centre',
    ];

    protected $table = 'training_venues';

    protected $fillable = [
        'name', 'type', 'partner_id', 'community_id', 'county', 'sub_county', 'ward',
        'capacity', 'contact_name', 'contact_phone', 'contact_email', 'summary', 'status',
    ];

    protected $casts = ['capacity' => 'integer'];

    /** The same rules whether staff add a venue or edit one later. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'partner_id' => 'nullable|integer|exists:partners,id',
            'community_id' => 'nullable|integer|exists:communities,id',
            'county' => 'required|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'capacity' => 'nullable|integer|min:0|max:2000',
            'contact_name' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:30',
            'contact_email' => 'nullable|email|max:190',
            'summary' => 'nullable|string|max:2000',
        ], [], [
            'partner_id' => 'Partner', 'community_id' => 'Community', 'county' => 'County',
            'sub_county' => 'Sub-county', 'capacity' => 'Trainee capacity',
        ]);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class, 'venue_id');
    }

    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class, 'venue_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? Str::headline($this->type);
    }

    public function placeLabel(): string
    {
        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }
}
