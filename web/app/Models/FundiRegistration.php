<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'tier', 'business_name', 'business_reg_no', 'years_in_business',
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

    public static function nextCode(): string
    {
        $count = static::whereNotNull('ef_code')->count();

        return 'EF-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
