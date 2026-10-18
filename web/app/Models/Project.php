<?php

namespace App\Models;

use App\Support\Execution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Project extends Model
{
    /**
     * Project categories and who may apply.
     * 'ctp' => CTP members are eligible; 'tiers' => E-Fundi tiers that are eligible.
     */
    public const CATEGORIES = [
        'attachment' => ['label' => 'CTP attachment', 'hint' => 'School-going CTPs and attachment applicants', 'ctp' => true, 'tiers' => ['attachment']],
        'internship' => ['label' => 'Graduate internship', 'hint' => 'Graduates seeking their first internship', 'ctp' => false, 'tiers' => ['fundi_intern']],
        'junior_fundi' => ['label' => 'Junior fundi job', 'hint' => 'Fundis who have completed an internship', 'ctp' => false, 'tiers' => ['junior_fundi']],
        'senior_fundi' => ['label' => 'Senior fundi job', 'hint' => 'Experienced senior fundis', 'ctp' => false, 'tiers' => ['senior_fundi']],
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'open' => 'Open for applications',
        'awarded' => 'Awarded',
        'closed' => 'Closed',
    ];

    /** Where a CTP who finished attachment, internship and their course is placed in the fundi pool. */
    public const CTP_POOL_TIERS = [
        'junior_fundi' => 'Junior fundi pool — finished attachment, internship and course',
        'senior_fundi' => 'Senior fundi pool — long industry experience',
    ];

    protected $fillable = [
        'reference', 'hirer_id', 'title', 'description', 'materials', 'category', 'trade',
        'county', 'ward', 'community_id', 'budget', 'duration', 'deadline', 'status',
        'awarded_at', 'posted_by',
    ];

    protected $casts = ['deadline' => 'date', 'awarded_at' => 'datetime', 'budget' => 'decimal:2'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class)->withDefault();
    }

    public function hirer(): BelongsTo
    {
        return $this->belongsTo(HirerRegistration::class, 'hirer_id', 'id', 'id')->withDefault();
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ProjectApplication::class);
    }

    /** The matching desk's rounds for this project (Module 6 §9.4). */
    public function matches(): HasMany
    {
        return $this->hasMany(ProjectMatch::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(ProjectApplication::class)->where('status', 'approved');
    }

    /** What the programme provides for this job (§14.5). Listed when the project is posted, deducted when the fundi is paid.
     *  Named apart from the `materials` text column, which is the office's plain-word description of the same list. */
    public function providedMaterials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    /** Module 8 — the construction file that opens on a buildable project (§11.2). */
    public function assessments(): HasMany
    {
        return $this->hasMany(SiteAssessment::class)->orderByDesc('visited_on')->orderByDesc('id');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class)->orderBy('position')->orderBy('id');
    }

    public function labourEntries(): HasMany
    {
        return $this->hasMany(LabourEntry::class)->orderByDesc('worked_on')->orderByDesc('id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(SiteInspection::class)->orderByDesc('scheduled_on')->orderByDesc('id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(VariationOrder::class)->orderByDesc('id');
    }

    public function materialsPlannedKsh(): float
    {
        return round($this->providedMaterials->sum(fn ($m) => $m->totalKsh()), 2);
    }

    public function materialsSuppliedKsh(): float
    {
        return round($this->providedMaterials->where('supplied_on', '!=', null)->sum(fn ($m) => $m->totalKsh()), 2);
    }

    public static function nextCode(): string
    {
        return 'PRJ-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? $this->category;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Categories this member is allowed to see and apply for. */
    public static function eligibleCategories(FundiRegistration|CtpRegistration|null $member): array
    {
        if (! $member) {
            return [];
        }

        return array_keys(array_filter(
            self::CATEGORIES,
            function (array $rule) use ($member) {
                if ($member instanceof CtpRegistration) {
                    return $rule['ctp'] || ($member->fundi_tier && in_array($member->fundi_tier, $rule['tiers'], true));
                }

                return in_array($member->tier, $rule['tiers'], true);
            }
        ));
    }

    /** Can this member see and apply for the project? */
    public function accepts(FundiRegistration|CtpRegistration|null $member): bool
    {
        return in_array($this->category, self::eligibleCategories($member), true);
    }

    /**
     * Can this member open the project at all? A CTP is a trainee: they read every
     * posted project to learn what work exists in their area, but only apply where
     * accepts() allows. An E-Fundi still sees only the levels they hold.
     */
    public function viewsTo(FundiRegistration|CtpRegistration|null $member): bool
    {
        if (! $member || $this->status === 'draft') {
            return false;
        }

        return $member instanceof CtpRegistration || $this->accepts($member);
    }

    /**
     * Every approved member, for the worker pickers. 'tier' and 'ctp' drive
     * fits(), so a list page can flag a mismatch without loading the record.
     */
    public static function assignableMembers(): array
    {
        $fundis = FundiRegistration::where('status', 'active')->orderBy('full_name')->get()
            ->map(fn (FundiRegistration $f) => [
                'type' => 'fundi',
                'id' => $f->id,
                'name' => $f->full_name,
                'code' => $f->ef_code ?: '—',
                'level' => FundiRegistration::tierLabel($f->tier),
                'skills' => implode(', ', (array) $f->specialisations),
                'tier' => $f->tier,
                'ctp' => false,
            ]);

        $ctps = CtpRegistration::where('status', 'active')->orderBy('full_name')->get()
            ->map(fn (CtpRegistration $c) => [
                'type' => 'ctp',
                'id' => $c->id,
                'name' => $c->full_name,
                'code' => $c->ctp_code ?: '—',
                'level' => $c->fundi_tier ? $c->gradeLabel() : 'CTP · '.str_replace('_', ' ', $c->track ?? 'member'),
                'skills' => $c->track ? Str::headline(str_replace('_', ' ', $c->track)).' track' : '',
                'tier' => $c->fundi_tier,
                'ctp' => true,
            ]);

        return $fundis->concat($ctps)->all();
    }

    /** Does this member's level fit the project category? */
    public static function fits(array $member, string $category): bool
    {
        $rule = self::CATEGORIES[$category] ?? null;

        if (! $rule) {
            return false;
        }

        return $member['ctp']
            ? ($rule['ctp'] || ($member['tier'] && in_array($member['tier'], $rule['tiers'], true)))
            : in_array($member['tier'], $rule['tiers'], true);
    }

    /**
     * The one way a project is given to a member, whichever screen reached it — an applicant the office picked on
     * the project page, or a fundi who accepted an invite on the matching desk (§9.4 step 6). Anyone else waiting on
     * this project is declined with the reason, and reassigning tells the previous member instead of losing them.
     */
    public function awardTo(ProjectApplication $application): ProjectApplication
    {
        $this->load('award');
        $previous = $this->award && $this->award->id !== $application->id ? $this->award : null;

        $previous?->update([
            'status' => 'rejected',
            'rejection_reason' => 'The project was reassigned to another member.',
            'reviewed_by' => auth()->id(),
        ]);

        $this->applications()->where('id', '!=', $application->id)->where('status', 'pending_review')
            ->update([
                'status' => 'rejected',
                'rejection_reason' => 'Another member was awarded this project.',
                'reviewed_by' => auth()->id(),
            ]);

        $application->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'rejection_reason' => null]);
        $this->update(['status' => 'awarded', 'awarded_at' => now()]);

        // §10.2 — an assignment is what a work order is generated from, so it happens here rather than on whichever
        // screen reached the award. A CTP attachment is recorded the same way a fundi job is.
        Execution::openFor($this, $application);

        return $application;
    }
}
