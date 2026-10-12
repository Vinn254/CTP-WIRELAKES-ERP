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
        'county', 'sub_county', 'ward', 'village', 'education_level', 'track',
        'referral_code', 'id_scan_path', 'certificate_path', 'passport_photo_path',
        'status', 'rejection_reason', 'ctp_code',
    ];

    protected $casts = ['dob' => 'date'];

    public static function nextCode(): string
    {
        $count = static::whereNotNull('ctp_code')->count();

        return 'CTP-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
