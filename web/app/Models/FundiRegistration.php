<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FundiRegistration extends Model
{
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

    protected $fillable = [
        'reference', 'full_name', 'national_id', 'phone', 'email', 'dob', 'gender',
        'tier', 'county', 'sub_county', 'ward', 'mobilised_by_ctp',
        'business_name', 'business_reg_no', 'years_in_business',
        'specialisations', 'service_counties', 'referee_name', 'referee_phone', 'referee_role',
        'id_scan_path', 'business_cert_path', 'trade_cert_paths', 'portfolio_paths',
        'status', 'user_id', 'rejection_reason', 'ef_code',
    ];

    protected $casts = [
        'dob' => 'date',
        'specialisations' => 'array',
        'service_counties' => 'array',
        'trade_cert_paths' => 'array',
        'portfolio_paths' => 'array',
    ];

    /** Short name of a tier — the part before the description dash. */
    public static function tierLabel(?string $tier): string
    {
        return $tier ? trim(Str::before(trim(self::TIERS[$tier] ?? $tier), '—')) : '';
    }

    /** The CTP who brought this fundi into the programme — the credit belongs to them. */
    public function mobiliser(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'mobilised_by_ctp');
    }

    /** The trainer role a senior fundi applies for (Module 4). One fundi holds at most one. */
    public function trainer(): HasOne
    {
        return $this->hasOne(Trainer::class, 'fundi_id');
    }

    public function placeLabel(): string
    {
        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }

    public function statusLabel(): string
    {
        return Str::headline(str_replace('_', ' ', $this->status));
    }

    public static function nextCode(): string
    {
        $count = static::whereNotNull('ef_code')->count();

        return 'EF-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
