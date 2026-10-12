<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtpRegistration extends Model
{
    public const TRACKS = [
        'school_leaver' => 'School-leaver training track',
        'junior' => 'Junior practical-check track',
    ];

    protected $fillable = [
        'reference', 'full_name', 'national_id', 'phone', 'email', 'dob', 'gender',
        'county', 'sub_county', 'ward', 'village', 'education_level', 'track', 'fundi_tier',
        'referral_code', 'id_scan_path', 'certificate_path', 'passport_photo_path',
        'status', 'user_id', 'rejection_reason', 'ctp_code',
    ];

    protected $casts = ['dob' => 'date'];

    /** Which fundi projects this CTP can see after finishing the programme. */
    public function poolLabel(): string
    {
        return $this->fundi_tier ? FundiRegistration::tierLabel($this->fundi_tier) : 'CTP trainee';
    }

    public static function nextCode(): string
    {
        $count = static::whereNotNull('ctp_code')->count();

        return 'CTP-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
