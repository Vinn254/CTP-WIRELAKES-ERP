<?php

namespace App\Models;

use App\Models\Concerns\NormalizedIdentity;
use App\Support\Accounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Module 5 — an E-Fundi: the programme's technical workforce. The person and their skill record
 * live here and nowhere else; the teaching role (Module 4), the job applications (Module 6) and the
 * money (Module 11) are separate rows that point back at this one.
 */
class FundiRegistration extends Model
{
    use NormalizedIdentity;

    public const TIERS = [
        'fundi_intern' => 'Fundi-Intern — graduated, no experience, seeking an internship',
        'attachment' => 'Attachment applicant — still in school, not yet graduated',
        'junior_fundi' => 'Junior Fundi — recent graduate with internship experience',
        'senior_fundi' => 'Senior Fundi — long industry experience',
    ];

    public const SPECIALISATIONS = [
        'Construction', 'Electrical', 'Plumbing', 'ICT', 'Networking', 'Robotics',
        'Programming', 'AI', 'E-Waste', 'Solar', 'Welding', 'Carpentry',
    ];

    /** No application waits on a payment — pending_payment is only a legacy row the office still settles. */
    public const STATUSES = [
        'pending_payment' => 'Pending payment',
        'pending_review' => 'Pending review',
        'active' => 'Active',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
        'blacklisted' => 'Blacklisted',
    ];

    /**
     *  — a fundi who is blocked is reopened, never deleted. Only a Director (a programme
     * executive) lifts a suspension or a blacklist, and the reason stays on the record.
     */
    public const BLOCKING = ['suspended', 'blacklisted'];

    /**  availability. The fundi sets it themselves; a job assignment moves it through Module 7. */
    public const AVAILABILITY = [
        'available' => 'Available for work',
        'on_job' => 'On a job',
        'on_leave' => 'On leave',
    ];

    /**
     * What each grade is allowed to do in the programme. A senior fundi carries the trade and is
     * the only grade that can train CTPs; a junior, an intern or an attachment applicant works under a
     * senior — they apply for the jobs their grade covers, mobilise in their area and collect e-waste.
     */
    public const CAPABILITIES = [
        'senior_fundi' => ['projects', 'supervise', 'mobilise', 'waste', 'train'],
        'junior_fundi' => ['projects', 'mobilise', 'waste'],
        'fundi_intern' => ['projects', 'mobilise', 'waste'],
        'attachment' => ['projects', 'waste'],
    ];

    /** The card a fundi's own interface is built from: what the ability is and what it opens. */
    public const CAPABILITY_LABELS = [
        'projects' => ['Apply for projects', 'Open jobs your grade covers, from My projects.'],
        'supervise' => ['Take on fundis under you', 'Junior fundis, interns and attachment applicants work under a senior fundi on a job.'],
        'mobilise' => ['Mobilise your area', 'Put households and communities forward and log what you see in the field.'],
        'waste' => ['Collect e-waste', 'Register what you collected and its condition, and hand it to the programme office.'],
        'train' => ['Train CTPs at a site', 'Apply for a site, get the office approval, then mark the CTPs you train.'],
    ];

    public const FEE_STATUSES = [
        'awaiting_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'waived' => 'Waived',
        'sponsored' => 'Sponsored — no fee',
    ];

    /** The paperwork on an application: form input name => the column that holds the path. */
    public const DOCUMENTS = [
        'passport_photo' => 'passport_photo_path',
        'id_scan' => 'id_scan_path',
        'business_cert' => 'business_cert_path',
        'trade_certs' => 'trade_cert_paths',
        'portfolio' => 'portfolio_paths',
    ];

    protected $fillable = [
        'reference', 'full_name', 'national_id', 'phone', 'email', 'dob', 'gender',
        'tier', 'county', 'sub_county', 'ward', 'mobilised_by_ctp',
        'business_name', 'business_reg_no', 'years_in_business',
        'specialisations', 'service_counties', 'referee_name', 'referee_phone', 'referee_role',
        'id_scan_path', 'business_cert_path', 'trade_cert_paths', 'portfolio_paths', 'passport_photo_path',
        'status', 'availability', 'availability_note',
        'fee_status', 'fee_amount', 'fee_method', 'fee_reference', 'fee_paid_at',
        'user_id', 'rejection_reason', 'ef_code',
    ];

    protected $casts = [
        'dob' => 'date',
        'specialisations' => 'array',
        'service_counties' => 'array',
        'trade_cert_paths' => 'array',
        'portfolio_paths' => 'array',
        'fee_amount' => 'float',
        'fee_paid_at' => 'datetime',
    ];

    /** The amount pre-filled when the office records money received on an E-Fundi application. */
    public static function feeAmount(): float
    {
        return (float) Setting::get('membership_fee_fundi', '1000');
    }

    public function feeSettled(): bool
    {
        return $this->fee_status !== 'awaiting_payment';
    }

    /** Short name of a tier — the part before the description dash. */
    public static function tierLabel(?string $tier): string
    {
        return $tier ? trim(Str::before(trim(self::TIERS[$tier] ?? $tier), '—')) : '';
    }

    /** The grade this person reads as on their own profile and on every office screen. */
    public function gradeLabel(): string
    {
        return self::tierLabel($this->tier) ?: 'E-Fundi';
    }

    /** The CTP who brought this fundi into the programme — the credit belongs to them. */
    public function mobiliser(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'mobilised_by_ctp');
    }

    /** The member account the office opened once the fundi was approved. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The teaching roles this fundi holds (Module 4). One row per SITE —  has a fundi training in
     * their own workshop or a community site, so being approved at Nyanda says nothing about Chemelod.
     */
    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class, 'fundi_id');
    }

    /** Projects this fundi has applied to. The project screens themselves stay in Module 6. */
    public function applications(): MorphMany
    {
        return $this->morphMany(ProjectApplication::class, 'applicant', 'applicant_type', 'applicant_id');
    }

    /** Module 6  — the times the matching desk put a project to this fundi, and what they answered. */
    public function invites(): HasMany
    {
        return $this->hasMany(ProjectMatch::class, 'fundi_id');
    }

    public function placeLabel(): string
    {
        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }

    /** The picture this member uploaded when they joined — the same column name a CTP uses. */
    public function photoPath(): ?string
    {
        return $this->passport_photo_path;
    }

    /**
     * The availability form, validated the same way on the office screen and on the fundi's own portal,
     * so the two can never disagree about what "on a job" means.
     */
    public static function availabilityFrom(Request $request): array
    {
        $data = $request->validate([
            'availability' => 'required|in:'.implode(',', array_keys(self::AVAILABILITY)),
            'availability_note' => 'nullable|string|max:300',
        ], [], ['availability' => 'Availability', 'availability_note' => 'Note']);

        return [
            'availability' => $data['availability'],
            'availability_note' => $data['availability_note'] ?? null,
        ];
    }

    /** The skill form, shared the same way. Skills only come from the programme's own trade list. */
    public static function skillsFrom(Request $request): array
    {
        $data = $request->validate([
            'specialisations' => 'required|array|min:1|max:12',
            'specialisations.*' => 'in:'.implode(',', self::SPECIALISATIONS),
            'service_counties' => 'nullable|array|max:47',
            'service_counties.*' => 'string|max:100',
        ], [], [
            'specialisations' => 'Skills', 'service_counties' => 'Counties covered',
        ]);

        return [
            'specialisations' => array_values(array_unique($data['specialisations'])),
            'service_counties' => array_values(array_unique($data['service_counties'] ?? [])),
        ];
    }

    /**
     * The same application a person fills online, typed in at the counter for someone who cannot reach
     * the website. It enters the same lifecycle — fee, review, E-Fundi ID, portal login — and the same
     * person can never be added twice: ID, phone and email are unique on this register.
     *
     * @return array<string, mixed> columns ready for create()
     */
    public static function officeApplicationFrom(Request $request): array
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'national_id' => ['required', 'max:20', Rule::unique('fundi_registrations', 'national_id')],
            'phone' => ['required', 'max:20', Rule::unique('fundi_registrations', 'phone')],
            'email' => ['required', 'email', Rule::unique('fundi_registrations', 'email')],
            'dob' => 'required|date|before:'.now()->subYears(16)->toDateString(),
            'gender' => 'required|in:male,female,other',
            'tier' => 'required|in:'.implode(',', array_keys(self::TIERS)),
            'county' => 'required|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'mobiliser_code' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:160',
            'business_reg_no' => 'nullable|string|max:60',
            'years_in_business' => 'nullable|integer|min:0|max:80',
            'specialisations' => 'required|array|min:1|max:12',
            'specialisations.*' => 'in:'.implode(',', self::SPECIALISATIONS),
            'service_counties' => 'required|array|min:1|max:47',
            'service_counties.*' => 'string|max:100',
            'referee_name' => 'required|string|max:150',
            'referee_phone' => 'required|string|max:20',
            'referee_role' => 'required|string|max:150',
            'id_scan' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'passport_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:1024',
            'business_cert' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'trade_certs' => 'nullable|array|max:6',
            'trade_certs.*' => 'file|mimes:jpg,jpeg,png,pdf|max:3072',
            'portfolio' => 'nullable|array|max:6',
            'portfolio.*' => 'image|mimes:jpg,jpeg,png|max:3072',
        ], [
            'national_id.unique' => 'That National ID is already on the E-Fundi register — open that record and reopen it, do not add the person twice.',
            'phone.unique' => 'That phone number is already on the E-Fundi register — open that record instead of adding the person twice.',
            'email.unique' => 'That email is already on the E-Fundi register — open that record instead of adding the person twice.',
        ], [
            'national_id' => 'National ID', 'sub_county' => 'Sub-county', 'mobiliser_code' => 'Mobilising CTP code',
            'years_in_business' => 'Years trading', 'trade_certs.*' => 'Trade certificate', 'portfolio.*' => 'Portfolio photo',
            'passport_photo' => 'Profile photo',
        ]);

        // One person, one account: the office cannot open a second record for somebody already on the programme.
        Accounts::guard($data);

        return [
            'reference' => 'EF-APP-'.strtoupper(bin2hex(random_bytes(3))),
            'status' => 'pending_review',
            'fee_status' => 'awaiting_payment',
            'availability' => 'available',
            'mobilised_by_ctp' => CtpRegistration::resolveReferrer($data['mobiliser_code'] ?? null, $data)?->id,
            ...collect($data)->except(array_merge(array_keys(self::DOCUMENTS), ['mobiliser_code']))->all(),
            'specialisations' => array_values(array_unique($data['specialisations'])),
            'service_counties' => array_values(array_unique($data['service_counties'])),
        ];
    }

    /** Attach the papers handed to the office, under this application's own folder on the private disk. */
    public function storeFiles(Request $request): void
    {
        $dir = 'registrations/fundi/'.$this->reference;

        foreach (['id_scan' => 'id_scan_path', 'business_cert' => 'business_cert_path', 'passport_photo' => 'passport_photo_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $this->{$column} = $request->file($input)->store($dir, 'local');
            }
        }

        foreach (['trade_certs' => 'trade_cert_paths', 'portfolio' => 'portfolio_paths'] as $input => $column) {
            $files = collect($request->file($input, []))->filter()->map(fn ($f) => $f->store($dir, 'local'))->values()->all();
            if ($files) {
                $this->{$column} = array_merge((array) $this->{$column}, $files);
            }
        }

        $this->save();
    }

    /**
     * The profile photo, taken or replaced at the counter. It is the picture every screen shows for
     * this member, so the old file goes with it — one face per profile.
     */
    public function attachPhoto(Request $request, string $input = 'passport_photo'): void
    {
        $old = $this->passport_photo_path;
        $this->update(['passport_photo_path' => $request->file($input)
            ->store('registrations/fundi/'.$this->reference, 'local')]);

        if ($old && $old !== $this->passport_photo_path) {
            Storage::disk('local')->delete($old);
        }
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline(str_replace('_', ' ', $this->status));
    }

    public function availabilityLabel(): string
    {
        return self::AVAILABILITY[$this->availability] ?? Str::headline(str_replace('_', ' ', (string) $this->availability));
    }

    /** Does this grade carry the ability? The single test every portal screen and route gates on. */
    public function can(string $ability): bool
    {
        return in_array($ability, self::CAPABILITIES[$this->tier] ?? [], true);
    }

    /** The abilities of this grade, in the order the interface lists them. */
    public function capabilities(): array
    {
        return self::CAPABILITIES[$this->tier] ?? [];
    }

    /** A senior fundi is the only grade the programme hands a training cohort to. */
    public function canTrain(): bool
    {
        return $this->can('train');
    }

    /** Only a verified fundi who says they are free can be matched or handed a job. */
    public function isMatchable(): bool
    {
        return $this->status === 'active' && $this->availability === 'available';
    }

    /** The sites this fundi trains CTPs at, with the office decision on each one. */
    public function trainingSites(): Collection
    {
        return $this->trainers()->with(['venue:id,name,type', 'trainings'])->get();
    }

    /**
     * The paperwork held on this record, in the order the office reads it. The paths are private —
     * they only open through the fundi's own file route, never from a url.
     *
     * @return array<string, array{label: string, path: string}>
     */
    public function documents(): array
    {
        $slots = [
            'passport_photo' => ['passport_photo_path', 'Profile photo'],
            'id_scan' => ['id_scan_path', 'National ID scan'],
            'business_cert' => ['business_cert_path', 'Business registration'],
        ];

        $out = [];
        foreach ($slots as $key => [$column, $label]) {
            $path = $this->{$column};
            if ($path && Storage::disk('local')->exists($path)) {
                $out[$key] = ['label' => $label, 'path' => $path];
            }
        }

        foreach (['trade_cert_paths' => 'Trade certificate', 'portfolio_paths' => 'Portfolio'] as $column => $label) {
            $n = 1;
            foreach ((array) $this->{$column} as $path) {
                if ($path && Storage::disk('local')->exists($path)) {
                    $out[$column.'-'.$n] = ['label' => $label.' '.$n, 'path' => $path];
                }
                $n++;
            }
        }

        return $out;
    }

    public static function nextCode(): string
    {
        $count = static::whereNotNull('ef_code')->count();

        return 'EF-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
