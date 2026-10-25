<?php

namespace App\Models;

use App\Models\Concerns\NormalizedIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HirerRegistration extends Model
{
    use NormalizedIdentity;

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
        'county', 'sub_county', 'ward', 'org_type', 'org_doc_path', 'status', 'rejection_reason', 'hirer_code',
    ];

    /** County · Sub-county · Ward, skipping whatever staff left blank. */
    public function locationLabel(): string
    {
        return collect([$this->county, $this->sub_county, $this->ward])->filter()->implode(' · ');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'hirer_id');
    }

    public static function nextCode(): string
    {
        $count = static::whereNotNull('hirer_code')->count();

        return 'HIR-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
