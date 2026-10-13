<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CtpRegistration extends Model
{
    public const TRACKS = [
        'school_leaver' => 'School-leaver training track',
        'junior' => 'Junior practical-check track',
    ];

    /** The lifecycle in §2.6 of the Module 2 workflow — three roles run on this one record. */
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** Which fundi projects this CTP can see after finishing the programme. */
    public function poolLabel(): string
    {
        return $this->fundi_tier ? FundiRegistration::tierLabel($this->fundi_tier) : 'CTP trainee';
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

    public function referralLink(): string
    {
        return route('register.ctp').'?ref='.urlencode($this->ctp_code ?? $this->reference);
    }

    /** The next mobilisation milestone, or null once both are earned. */
    public function nextMilestone(): ?int
    {
        foreach (array_keys(self::MOBILISATION_TIERS) as $target) {
            if ($this->verifiedReferrals() < $target) {
                return $target;
            }
        }

        return null;
    }

    /** The reward earned by the referrals counted so far. */
    public function discountLabel(): string
    {
        $count = $this->verifiedReferrals();
        $earned = null;

        foreach (self::MOBILISATION_TIERS as $target => $reward) {
            if ($count >= $target) {
                $earned = $reward;
            }
        }

        return $earned ?? 'No term discount yet — '.$count.' verified referral'.($count === 1 ? '' : 's');
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
            $this->status === 'pending_payment' => 'Pay the one-time membership fee to reach the review queue',
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

    public function initials(): string
    {
        return collect(explode(' ', trim((string) $this->full_name)))->filter()
            ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
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
