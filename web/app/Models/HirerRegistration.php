<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HirerRegistration extends Model
{
    public const ORG_TYPES = [
        'organisation' => 'Organisation',
        'school' => 'School',
        'business' => 'Business',
        'community_group' => 'Community group',
        'faith_based' => 'Church / Faith-based',
        'ngo_cbo' => 'NGO / CBO',
        'government' => 'Government office',
        'other' => 'Other',
    ];

    protected $fillable = [
        'reference', 'contact_name', 'organisation', 'phone', 'email',
        'county', 'org_type', 'org_doc_path', 'status', 'rejection_reason', 'hirer_code',
    ];

    public static function nextCode(): string
    {
        $count = static::whereNotNull('hirer_code')->count();

        return 'HIR-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
