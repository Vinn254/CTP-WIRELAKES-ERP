<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A senior fundi licensed to train CTPs (§7.8). The person stays Module 5's record — this row is only
 * the teaching role: where they teach, what they can teach, the hours they give and what they are paid.
 */
class Trainer extends Model
{
    public const STATUSES = [
        'applied' => 'Applied',
        'approved' => 'Approved trainer',
        'declined' => 'Declined',
        'suspended' => 'Suspended',
    ];

    /** Only a fundi who has worked the trade may teach it — an intern or a school attachment cannot. */
    public const TEACHING_TIERS = ['junior_fundi', 'senior_fundi'];

    protected $fillable = [
        'fundi_id', 'venue_id', 'county', 'sub_county', 'ward', 'teaches',
        'weekly_hours', 'rate_ksh', 'status', 'note', 'reviewed_by',
    ];

    protected $casts = ['teaches' => 'array', 'rate_ksh' => 'decimal:2'];

    /**
     * The same rules whether the office puts a fundi forward or the fundi applies to train
     * (the portal sets fundi_id from the signed-in member, never from the form).
     */
    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'fundi_id' => 'required|integer|exists:fundi_registrations,id',
            'venue_id' => 'nullable|integer|exists:training_venues,id',
            'county' => 'required|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'teaches' => 'required|array|min:1',
            'teaches.*' => 'in:'.implode(',', Cohort::FAMILIES),
            'weekly_hours' => 'nullable|integer|min:0|max:80',
            'rate_ksh' => 'nullable|numeric|min:0|max:999999',
            'note' => 'nullable|string|max:500',
        ], [], [
            'fundi_id' => 'Fundi', 'teaches' => 'What they can teach', 'weekly_hours' => 'Hours a week',
            'rate_ksh' => 'Training rate', 'county' => 'County', 'sub_county' => 'Sub-county',
        ]);

        $data['teaches'] = array_values(array_unique($data['teaches']));

        return $data;
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(TrainingVenue::class, 'venue_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class, 'trainer_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    /** Either the venue they are posted at, or the area they offered to teach in. */
    public function placeLabel(): string
    {
        return $this->venue
            ? $this->venue->name.' ('.$this->venue->placeLabel().')'
            : collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }

    /** A trainer may only be handed a cohort in a family they said they can teach. */
    public function canTeach(string $family): bool
    {
        return in_array($family, (array) $this->teaches, true);
    }
}
