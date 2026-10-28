<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A person the programme has licensed to train at ONE SITE. That person is not always an E-Fundi:
 * a facilitator the office employs and an applicant taken on at Careers stand in the same queue, and all three
 * work the one trainer desk. What shapes the desk is the programmes they are licensed for, never the record they
 * come from. The row is only the teaching role at a named venue — which trainings they can take there, the hours
 * they give, what they are paid and the office's decision on that site. A trainer who wants a second site is
 * posted again, and the second site is approved or declined on its own.
 */
class Trainer extends Model
{
    public const STATUSES = [
        'applied' => 'Applied for this site',
        'approved' => 'Approved for this site',
        'declined' => 'Declined for this site',
        'suspended' => 'Suspended',
    ];

    /** Only a senior fundi carries the trade far enough to teach it. A junior, intern or
     *  attachment applicant works under a senior — they do not run a cohort. The rule is checked only
     *  of an E-Fundi holder: a staff account or an accepted applicant is licensed by the office directly. */
    public const TEACHING_TIERS = ['senior_fundi'];

    /** Whoever stands behind a teaching posting. The desk is the same for all three — only the record holding
     *  the person differs, so a programme's trainers are never split across three different screens. */
    public const HOLDERS = [
        'fundi' => 'E-Fundi',
        'staff' => 'Staff account',
        'applicant' => 'Careers applicant',
    ];

    /** Where each kind of holder actually lives. */
    public const HOLDER_COLUMN = [
        'fundi' => 'fundi_id',
        'staff' => 'user_id',
        'applicant' => 'job_application_id',
    ];

    protected $fillable = [
        'holder_type', 'fundi_id', 'user_id', 'job_application_id', 'venue_id', 'county', 'sub_county', 'ward',
        'weekly_hours', 'rate_ksh', 'status', 'note', 'reviewed_by',
    ];

    protected $casts = ['rate_ksh' => 'decimal:2'];

    private static array $trainingAccounts = [];

    /**
     * The same rules whether the office puts someone forward for a site or the person applies for it themselves
     * (the portal sets fundi_id from the signed-in member, never from the form). The site is named on every
     * application because that is what the office approves. A posting is held by exactly one record — an E-Fundi,
     * a staff account or an accepted Careers applicant — and the tier rule is checked of an E-Fundi only, since
     * the office licenses the other two by hand.
     * 'trainings' is a list of ids from the programme's own catalogue and is synced onto the pivot.
     */
    public static function dataFrom(Request $request): array
    {
        // A single "who is training" pick from the office form arrives as kind:id and is spread onto its own column.
        $chosen = (string) $request->input('holder', '');
        if (str_contains($chosen, ':')) {
            [$type, $id] = explode(':', $chosen, 2);
            $type = isset(self::HOLDERS[$type]) ? $type : 'fundi';
            $request->merge(['holder_type' => $type, self::HOLDER_COLUMN[$type] => (int) $id]);
        }

        $data = $request->validate([
            'holder_type' => 'nullable|string|in:'.implode(',', array_keys(self::HOLDERS)),
            'fundi_id' => 'nullable|integer|exists:fundi_registrations,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'job_application_id' => 'nullable|integer|exists:job_applications,id',
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
            'fundi_id' => 'E-Fundi', 'user_id' => 'Staff account', 'job_application_id' => 'Applicant',
            'holder_type' => 'Who is training', 'trainings' => 'What they can teach', 'weekly_hours' => 'Hours a week',
            'rate_ksh' => 'Training rate', 'county' => 'County', 'sub_county' => 'Sub-county',
            'venue_id' => 'Site',
        ]);

        $data['holder_type'] = $data['holder_type'] ?? 'fundi';
        $column = self::HOLDER_COLUMN[$data['holder_type']];
        $data['fundi_id'] = $data['user_id'] = $data['job_application_id'] = null;
        $data[$column] = $request->input($column);

        abort_unless(filled($data[$column]), 422,
            'Say which of our people takes this training — an E-Fundi, a staff account or a Careers applicant.');

        $data['trainings'] = array_values(array_unique(array_map('intval', $data['trainings'])));

        return $data;
    }

    /** The row this person already holds for that site, if any — one site, one application. */
    public static function forSite(int $fundiId, int $venueId): ?self
    {
        return static::forHolder('fundi', $fundiId, $venueId);
    }

    /** One posting per holder and site, whichever of the three records the holder comes from. */
    public static function forHolder(string $type, ?int $holderId, int $venueId): ?self
    {
        if (! $holderId) {
            return null;
        }

        return self::where('holder_type', $type)
            ->where(self::HOLDER_COLUMN[$type], $holderId)
            ->where('venue_id', $venueId)
            ->first();
    }

    /**
     * Every approved posting the signed-in account stands behind — matched on the account's own id, or on its own
     * email for an E-Fundi or an accepted applicant. Never on an id from the url, so a trainer can only ever open
     * their own week sheet and their own cohorts.
     *
     * @return Collection<int, self>
     */
    public static function forAccount(?User $user): Collection
    {
        if (! $user) {
            return new Collection;
        }

        $held = self::approved()->where('user_id', $user->id)->get();

        if (! $user->email) {
            return $held;
        }

        $email = Str::lower($user->email);
        $fundis = FundiRegistration::whereRaw('LOWER(email) = ?', [$email])->pluck('id');
        $applicants = JobApplication::whereRaw('LOWER(email) = ?', [$email])->pluck('id');

        if ($fundis->isEmpty() && $applicants->isEmpty()) {
            return $held;
        }

        return $held->merge(self::where('status', 'approved')->where(function ($q) use ($fundis, $applicants) {
            $q->when($fundis->isNotEmpty(), fn ($one) => $one->orWhereIn('fundi_id', $fundis));
            $q->when($applicants->isNotEmpty(), fn ($one) => $one->orWhereIn('job_application_id', $applicants));
        })->get())->unique('id')->values();
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
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

    /** The person behind the posting, whichever record holds them. */
    public function personName(): string
    {
        return match ($this->holder_type) {
            'staff' => $this->staff?->name ?? 'A staff account',
            'applicant' => $this->applicant?->name ?? 'An applicant',
            default => $this->fundi?->full_name ?? 'An E-Fundi',
        };
    }

    /**
     * Whether the signed-in account itself holds a posting — used to label a mark on a trainee's record as the
     * trainer's rather than the office's, whichever of the three records that trainer comes from.
     */
    public static function trainsFrom(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return self::$trainingAccounts[$userId] ??= static::forAccount(User::find($userId))->isNotEmpty();
    }

    public function holderLabel(): string
    {
        return self::HOLDERS[$this->holder_type] ?? self::HOLDERS['fundi'];
    }

    /** The code the programme knows this person by — an E-Fundi ID, a CTP-grade staff email, or an application. */
    public function holderCode(): string
    {
        return match ($this->holder_type) {
            'staff' => $this->staff?->email ?? '',
            'applicant' => $this->applicant?->id_number ?: ($this->applicant?->email ?? ''),
            default => $this->fundi?->ef_code ?? '',
        };
    }

    /** The id column that holds this posting's person, for the desks that look a trainer up by holder. */
    public function holderColumn(): string
    {
        return self::HOLDER_COLUMN[$this->holder_type] ?? self::HOLDER_COLUMN['fundi'];
    }

    public function holderId(): ?int
    {
        return $this->{$this->holderColumn()} ? (int) $this->{$this->holderColumn()} : null;
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
