<?php

namespace App\Models;

use App\Models\Concerns\NormalizedIdentity;
use App\Support\Accounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CtpRegistration extends Model
{
    use NormalizedIdentity;

    public const TRACKS = [
        'school_leaver' => 'School-leaver training track',
        'junior' => 'Junior practical-check track',
    ];

    /** The lifecycle in  of the Module 2 workflow — three roles run on this one record. */
    public const STATUSES = [
        'pending_payment' => 'Pending payment',
        'pending_review' => 'Pending review',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'graduated' => 'Graduated',
        'deployed' => 'Deployed',
        'rejected' => 'Rejected',
    ];

    /** Statuses that let the CTP sign in, get mobilisation credit and take projects. */
    public const WORKING = ['active', 'graduated', 'deployed'];

    public const FEE_STATUSES = [
        'awaiting_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'waived' => 'Waived',
        'sponsored' => 'Sponsored',
    ];

    /** How the person got into the programme: their own application, or the office typing it in. */
    public const SOURCES = [
        'self' => 'Applied on the website',
        'office' => 'Added from the office',
    ];

    public const EDUCATION_LEVELS = [
        'none' => 'No formal education', 'primary' => 'Primary', 'secondary' => 'Secondary',
        'certificate' => 'Certificate', 'diploma' => 'Diploma', 'degree' => 'Degree',
        'postgraduate' => 'Postgraduate',
    ];

    public const MOBILISATION_TIERS = [
        15 => '50% off the current term',
        30 => '100% off the current term (sponsorship credit)',
    ];

    protected $fillable = [
        'reference', 'full_name', 'national_id', 'phone', 'email', 'dob', 'gender',
        'county', 'sub_county', 'ward', 'village', 'gps_lat', 'gps_lng',
        'education_level', 'year_left_school', 'certificates_held',
        'track', 'fundi_tier', 'referral_code', 'referred_by', 'referred_at',
        'id_scan_path', 'certificate_path', 'passport_photo_path',
        'service_counties', 'available_for_mobilisation', 'trainer_opt_in',
        'status', 'status_note', 'fee_status', 'fee_amount', 'fee_method', 'fee_reference', 'fee_paid_at',
        'sponsor_id', 'source', 'added_by',
        'user_id', 'approved_by', 'approved_at', 'rejection_reason', 'ctp_code',
    ];

    protected $casts = [
        'dob' => 'date',
        'certificates_held' => 'array',
        'service_counties' => 'array',
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'available_for_mobilisation' => 'boolean',
        'trainer_opt_in' => 'boolean',
        'fee_amount' => 'float',
        'fee_paid_at' => 'datetime',
        'referred_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    /** Everyone this CTP brought in. */
    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    /** E-Fundis who registered because this CTP mobilised them. */
    public function mobilisedFundis(): HasMany
    {
        return $this->hasMany(FundiRegistration::class, 'mobilised_by_ctp');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The partner named on a CTP's record — the office settles what that partner covers. */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'sponsor_id');
    }

    /** The staff member who typed this application in at the office. */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Communities this CTP looks after or has put forward (Module 3). */
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_ctp', 'ctp_id', 'community_id')
            ->withPivot(['role', 'assigned_at'])->withTimestamps();
    }

    public function needsRecorded(): HasMany
    {
        return $this->hasMany(Need::class, 'assessed_by_ctp');
    }

    public function beneficiariesRegistered(): HasMany
    {
        return $this->hasMany(Beneficiary::class, 'created_by_ctp');
    }

    /** Training seats — applied for by the CTP or given by the office (Module 4). */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'ctp_id');
    }

    /** The CTP's live training: a seat they hold, with its cohort and any certificate on it. */
    public function activeEnrollments()
    {
        return $this->enrollments()
            ->whereIn('state', ['pending', 'approved'])
            ->with(['cohort.venue:id,name,county,sub_county,ward', 'cohort.trainer.fundi:id,full_name',
                'cohort.trainer.staff:id,name', 'cohort.trainer.applicant:id,name', 'certification'])
            ->latest('id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function nextCode(): string
    {
        $highest = self::where('ctp_code', 'like', 'CTP-%')
            ->selectRaw('MAX(CAST(SUBSTRING(ctp_code, 5) AS UNSIGNED)) n')
            ->value('n');

        return 'CTP-'.str_pad((string) ((int) $highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /** The application number the person keeps — the CTP ID is only issued on approval. */
    public static function newReference(): string
    {
        return 'CTP-APP-'.strtoupper(bin2hex(random_bytes(3)));
    }

    /** The papers an application carries, keyed by the form input that uploads them. */
    public const DOCUMENTS = [
        'id_scan' => 'id_scan_path',
        'certificate' => 'certificate_path',
        'passport_photo' => 'passport_photo_path',
    ];

    /** The amount pre-filled when the office records money received on a CTP application. */
    public static function feeAmount(): float
    {
        return (float) Setting::get('membership_fee_ctp', '1000');
    }

    /**
     * The application questions, asked the same way whether the person fills the form themselves or
     * an officer types it in at the office. An office entry can arrive without the papers — they are
     * photographed at the counter and attached to the profile later.
     */
    public static function applicationRules(bool $fromOffice = false): array
    {
        return [
            'full_name' => 'required|string|max:150',
            'national_id' => ['required', 'max:20', Rule::unique('ctp_registrations', 'national_id')],
            'phone' => ['required', 'max:20', Rule::unique('ctp_registrations', 'phone')],
            'email' => ['required', 'email', Rule::unique('ctp_registrations', 'email')],
            'dob' => 'required|date|before:'.now()->subYears(16)->toDateString(),
            'gender' => 'required|in:male,female,other',
            'county' => 'required|string|max:100',
            'sub_county' => 'required|string|max:100',
            'ward' => 'required|string|max:100',
            'village' => 'required|string|max:100',
            'education_level' => 'required|in:'.implode(',', array_keys(self::EDUCATION_LEVELS)),
            'track' => 'required|in:'.implode(',', array_keys(self::TRACKS)),
            'referral_code' => 'nullable|string|max:20',
            'sponsor_id' => 'nullable|integer|exists:partners,id',
            'id_scan' => ($fromOffice ? 'nullable' : 'required').'|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'certificate' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'passport_photo' => ($fromOffice ? 'nullable' : 'required').'|image|mimes:jpg,jpeg,png|max:1024',
        ];
    }

    public static function applicationLabels(): array
    {
        return [
            'national_id' => 'National ID', 'id_scan' => 'National ID scan',
            'passport_photo' => 'Profile photo', 'dob' => 'Date of birth',
            'sub_county' => 'Sub-county', 'sponsor_id' => 'Sponsoring partner',
        ];
    }

    /**
     * Open a CTP application — from the public form or the office screen. A promoter a partner is
     * sponsoring has no fee to pay, so the application starts in the review queue instead.
     */
    public static function startApplication(array $data, bool $fromOffice = false): self
    {
        // One person, one account: the same email, phone or ID cannot open a second record anywhere in the programme.
        Accounts::guard($data);

        // Only the office settles a fee. A person who names a sponsor on the public form is believed after the
        // partner is checked, so the application still waits at the fee step until then.
        $sponsored = $fromOffice && ! empty($data['sponsor_id']);
        $referrer = static::resolveReferrer($data['referral_code'] ?? null, $data);

        return static::create([
            'reference' => static::newReference(),
            // No payment gates an application — it goes straight to the review queue.
            'status' => 'pending_review',
            'fee_status' => $sponsored ? 'sponsored' : 'awaiting_payment',
            'fee_paid_at' => $sponsored ? now() : null,
            'referred_by' => $referrer?->id,
            'referred_at' => $referrer ? now() : null,
            'source' => $fromOffice ? 'office' : 'self',
            'added_by' => $fromOffice ? auth()->id() : null,
            ...collect($data)->except(array_keys(self::DOCUMENTS))->all(),
        ]);
    }

    /** Store the uploaded papers under this application's own folder on the local disk. */
    public function storeFiles(Request $request, array $map = self::DOCUMENTS): void
    {
        foreach ($map as $input => $column) {
            if ($request->hasFile($input)) {
                $this->{$column} = $request->file($input)->store('registrations/ctp/'.$this->reference, 'local');
            }
        }
        $this->save();
    }

    /**
     * The profile photo, taken or replaced at the counter. It is the picture every screen shows for
     * this member, so the old file is removed with it — one face per profile.
     */
    public function attachPhoto(Request $request, string $input = 'passport_photo'): void
    {
        $old = $this->passport_photo_path;
        $this->update(['passport_photo_path' => $request->file($input)
            ->store('registrations/ctp/'.$this->reference, 'local')]);

        if ($old && $old !== $this->passport_photo_path) {
            Storage::disk('local')->delete($old);
        }
    }

    /**
     * Match the typed referral code to an existing CTP. Self-referral is impossible here:
     * a brand-new record has no code, and anyone re-registering under their own phone, ID or
     * email is the same person, so the referral is dropped instead of counted.
     */
    public static function resolveReferrer(?string $code, array $data): ?self
    {
        $code = Str::upper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        $referrer = static::whereIn('status', self::WORKING)->where('ctp_code', $code)->first();

        if (! $referrer) {
            return null;
        }

        $samePerson = Accounts::idNumber($referrer->national_id) === Accounts::idNumber($data['national_id'] ?? null)
            || Accounts::phone($referrer->phone) === Accounts::phone($data['phone'] ?? null)
            || Accounts::email($referrer->email) === Accounts::email($data['email'] ?? null);

        return $samePerson ? null : $referrer;
    }

    /**
     * What this person reads as, on their own profile and on every office screen. The office moving a CTP into a
     * fundi pool changes the grade they are shown from then on — the record stays one row and only the grade on it
     * moves. It is also what decides which projects they are offered.
     */
    public function gradeLabel(): string
    {
        return $this->fundi_tier ? FundiRegistration::tierLabel($this->fundi_tier) : 'CTP trainee';
    }

    /**
     * The CTPs who saw a training run through to the end of its track, or hold a certificate an executive approved
     * — the alumni the office then moves on into a fundi pool. Their seats, cohort and certificate come with them so
     * a register reads one row per person without a query each.
     */
    public static function alumni(): Builder
    {
        return static::query()
            ->where(fn ($q) => $q
                ->whereHas('enrollments', fn ($e) => $e->where('stage', 'completed'))
                ->orWhereHas('enrollments.certification', fn ($c) => $c->where('status', 'approved')))
            ->with(['enrollments' => fn ($q) => $q->with([
                'cohort:id,reference,training_id,ends_on',
                'cohort.training:id,name',
                'certification:id,enrollment_id,certificate_code,theory_score,practical_score,attendance_pct,status,approved_on,reason',
            ])]);
    }

    /** The runs of training this CTP finished, with the certificate on each. Newest first. */
    public function finishedTrainings(): Collection
    {
        $seats = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->with(['cohort.training', 'certification'])->get();

        return $seats->filter(fn (Enrollment $e) => $e->stage === 'completed')->sortByDesc('id')->values();
    }

    /** The certificate that qualifies them — the newest one an executive approved, or null while none is live. */
    public function liveCertification(): ?Certification
    {
        return $this->finishedTrainings()
            ->map(fn (Enrollment $e) => $e->certification)
            ->filter(fn (?Certification $c) => $c?->isLive())
            ->first();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function trackLabel(): string
    {
        return self::TRACKS[$this->track] ?? Str::headline($this->track);
    }

    public function feeSettled(): bool
    {
        return $this->fee_status !== 'awaiting_payment';
    }

    public function payable(): bool
    {
        return in_array($this->status, self::WORKING, true);
    }

    /** Referrals that count toward the 15 / 30 rule — approved and still standing. */
    public function verifiedReferrals(): int
    {
        return $this->referrals()->whereIn('status', self::WORKING)->count();
    }

    /** Referrals counted per term — only those verified inside the current term window. */
    public function termReferrals()
    {
        return $this->referrals()
            ->whereIn('status', self::WORKING)
            ->where('referred_at', '>=', self::currentTerm()['start']);
    }

    public function verifiedReferralsThisTerm(): int
    {
        return $this->termReferrals()->count();
    }

    /**
     * Mobilisation credit is measured on people, not on one registration type:
     * a trainee who joined the programme or a fundi who registered counts the same.
     */
    public function peopleBrought(): int
    {
        return $this->verifiedReferrals() + $this->mobilisedFundis()->where('status', 'active')->count();
    }

    public function peopleBroughtThisTerm(): int
    {
        $since = self::currentTerm()['start'];

        return $this->termReferrals()->count()
            + $this->mobilisedFundis()->where('status', 'active')
                ->where('created_at', '>=', $since)->count();
    }

    /** The same people as a list newest first, shaped the way Module 3 shows them. */
    public function broughtList(): Collection
    {
        $trainees = $this->referrals()->with('referrer')->latest('id')->get()
            ->map(fn (self $c) => self::asBrought($c));
        $fundis = $this->mobilisedFundis()->with('mobiliser')->latest('id')->get()
            ->map(fn (FundiRegistration $f) => self::asBrought($f));

        return $trainees->concat($fundis)->sortByDesc('on')->values();
    }

    /** One shape for both kinds of person, so any screen can list them together. */
    public static function asBrought(CtpRegistration|FundiRegistration $person): array
    {
        return $person instanceof CtpRegistration ? [
            'name' => $person->full_name, 'code' => $person->ctp_code ?: $person->reference,
            'kind' => $person->gradeLabel(), 'status' => $person->status, 'label' => $person->statusLabel(),
            'place' => collect([$person->ward, $person->sub_county, $person->county])->filter()->join(' · '),
            'mobiliser' => $person->referrer->full_name ?? '—', 'on' => $person->referred_at ?? $person->created_at,
        ] : [
            'name' => $person->full_name, 'code' => $person->ef_code ?: $person->reference,
            'kind' => FundiRegistration::tierLabel($person->tier) ?: 'E-Fundi',
            'status' => $person->status, 'label' => $person->statusLabel(),
            'place' => $person->placeLabel(),
            'mobiliser' => $person->mobiliser->full_name ?? '—', 'on' => $person->created_at,
        ];
    }

    public function referralLink(): string
    {
        return route('register.ctp').'?ref='.urlencode($this->ctp_code ?? $this->reference);
    }

    /** The next mobilisation milestone, or null once both are earned. */
    public function nextMilestone(): ?int
    {
        foreach (array_keys(self::MOBILISATION_TIERS) as $target) {
            if ($this->peopleBrought() < $target) {
                return $target;
            }
        }

        return null;
    }

    /** The reward earned by the people mobilised so far. */
    public function discountLabel(): string
    {
        $count = $this->peopleBrought();
        $earned = null;

        foreach (self::MOBILISATION_TIERS as $target => $reward) {
            if ($count >= $target) {
                $earned = $reward;
            }
        }

        return $earned ?? 'No term discount yet — '.$count.' '.($count === 1 ? 'person brought' : 'people brought');
    }

    /** Kenyan programme terms — mobilisation credit is measured inside the current one. */
    public static function currentTerm(): array
    {
        $year = now()->year;

        [$month, $label] = match (true) {
            now()->month <= 4 => ['01', 'Jan–Apr'],
            now()->month <= 8 => ['05', 'May–Aug'],
            default => ['09', 'Sep–Dec'],
        };

        return ['label' => $label.' '.$year, 'start' => Carbon::parse($year.'-'.$month.'-01')];
    }

    /** The step of the CTP path the person is standing on, shown on their own dashboard. */
    public function stageLabel(): string
    {
        return match (true) {
            $this->status === 'pending_payment' => 'Programme office is reviewing your application and documents',
            $this->status === 'pending_review' => 'Programme office is reviewing your application and documents',
            $this->status === 'rejected' => 'Application closed — see the reason on your record',
            $this->status === 'suspended' => 'Suspended — speak to your County Coordinator',
            $this->status === 'deployed' => 'Deployed — you are working on projects in the field',
            $this->status === 'graduated' => 'Certified — waiting on deployment into projects and internship',
            default => $this->track === 'junior'
                ? 'Active CTP — book your practical check and certification check'
                : 'Active CTP — enrolled in the training cohort',
        };
    }

    public function locationLabel(): string
    {
        return collect([$this->county, $this->sub_county, $this->ward, $this->village])->filter()->join(' · ');
    }

    /** The picture this member uploaded when they joined — shown on their profile everywhere. */
    public function photoPath(): ?string
    {
        return $this->passport_photo_path;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim((string) $this->full_name)))->filter()
            ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    }

    /** What a printed paper writes: the given name and the surname, never a middle name or a title. */
    public function paperName(): string
    {
        $words = collect(explode(' ', trim((string) $this->full_name)))->filter()->values();

        return $words->count() < 3 ? $words->join(' ') : $words->first().' '.$words->last();
    }

    /** Missing profile information, so the profile page can show what staff still need to capture. */
    public function profileGaps(): array
    {
        $gaps = [];
        if (! $this->gps_lat || ! $this->gps_lng) {
            $gaps[] = 'GPS pin of their home / work area';
        }
        if (! $this->year_left_school) {
            $gaps[] = 'Year they left school';
        }
        if (! count((array) $this->service_counties)) {
            $gaps[] = 'Counties they will serve';
        }
        if ($this->track === 'junior' && ! count((array) $this->certificates_held)) {
            $gaps[] = 'Certificates held (junior track must be verified)';
        }

        return $gaps;
    }
}
