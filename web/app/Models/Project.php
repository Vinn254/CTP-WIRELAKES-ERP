<?php

namespace App\Models;

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
        'county', 'ward', 'budget', 'duration', 'deadline', 'status',
        'awarded_at', 'posted_by',
    ];

    protected $casts = ['deadline' => 'date', 'awarded_at' => 'datetime', 'budget' => 'decimal:2'];

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

    public function award(): HasOne
    {
        return $this->hasOne(ProjectApplication::class)->where('status', 'approved');
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
                'level' => $c->fundi_tier ? $c->poolLabel() : 'CTP · '.str_replace('_', ' ', $c->track ?? 'member'),
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
}
