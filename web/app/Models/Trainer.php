<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A fundi licensed to train CTPs at ONE SITE (§7.8, §8.8). The person stays Module 5's record — this row
 * is only the teaching role at a named venue: which of the programme's trainings they can take there,
 * the hours they give, what they are paid and the office's decision on that site. A fundi who wants to
 * teach at a second site applies again, and the second site is approved or declined on its own.
 */
class Trainer extends Model
{
    public const STATUSES = [
        'applied' => 'Applied for this site',
        'approved' => 'Approved for this site',
        'declined' => 'Declined for this site',
        'suspended' => 'Suspended',
    ];

    /** Only a senior fundi carries the trade far enough to teach it (§8.2). A junior, intern or
     *  attachment applicant works under a senior — they do not run a cohort. */
    public const TEACHING_TIERS = ['senior_fundi'];

    protected $fillable = [
        'fundi_id', 'venue_id', 'county', 'sub_county', 'ward',
        'weekly_hours', 'rate_ksh', 'status', 'note', 'reviewed_by',
    ];

    protected $casts = ['rate_ksh' => 'decimal:2'];

    /**
     * The same rules whether the office puts a fundi forward for a site or the fundi applies for it
     * themselves (the portal sets fundi_id from the signed-in member, never from the form). The site is
     * named on every application because that is what the office approves.
     * 'trainings' is a list of ids from the programme's own catalogue and is synced onto the pivot.
     */
    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'fundi_id' => 'required|integer|exists:fundi_registrations,id',
            'venue_id' => 'required|integer|exists:training_venues,id',
            'county' => 'required|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'trainings' => 'required|array|min:1',
            'trainings.*' => 'integer|exists:trainings,id',
            'weekly_hours' => 'nullable|integer|min:0|max:80',
            'rate_ksh' => 'nullable|numeric|min:0|max:999999',
            'note' => 'nullable|string|max:500',
        ], [
            'venue_id.required' => 'Name the site you want to train at — the office approves a trainer for a site, not for the whole programme.',
        ], [
            'fundi_id' => 'Fundi', 'trainings' => 'What they can teach', 'weekly_hours' => 'Hours a week',
            'rate_ksh' => 'Training rate', 'county' => 'County', 'sub_county' => 'Sub-county',
            'venue_id' => 'Site',
        ]);

        $data['trainings'] = array_values(array_unique(array_map('intval', $data['trainings'])));

        return $data;
    }

    /** The row this fundi already holds for that site, if any — one site, one application. */
    public static function forSite(int $fundiId, int $venueId): ?self
    {
        return self::where('fundi_id', $fundiId)->where('venue_id', $venueId)->first();
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id');
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class, 'trainer_training')->orderBy('name');
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
        if ($this->venue) {
            return trim($this->venue->name.' — '.$this->venue->placeLabel(), ' —');
        }

        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }

    /** A trainer may only be handed a cohort in a training they are registered for. */
    public function canTeach(int $trainingId): bool
    {
        return $this->relationLoaded('trainings')
            ? $this->trainings->contains('id', $trainingId)
            : $this->trainings()->where('trainings.id', $trainingId)->exists();
    }

    public function teachingLabel(): string
    {
        return $this->trainings->pluck('name')->join(', ');
    }
}
