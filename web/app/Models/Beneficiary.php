<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** A person, household or business in a community that a project reached. */
class Beneficiary extends Model
{
    public const TYPES = [
        'student' => 'Student',
        'youth' => 'Youth',
        'household' => 'Household',
        'business' => 'Business',
        'organisation' => 'Organisation',
    ];

    protected $fillable = [
        'community_id', 'project_id', 'name', 'age', 'gender', 'type',
        'benefited_on', 'id_number', 'id_consent', 'created_by_ctp', 'created_by_user',
    ];

    protected $casts = [
        'benefited_on' => 'date',
        'age' => 'integer',
        'id_consent' => 'boolean',
    ];

    /** The register form fields — the same rules for a CTP in the portal and staff on the community page. */
    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'age' => 'nullable|integer|min:1|max:120',
            'gender' => 'nullable|in:male,female,other',
            'type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'benefited_on' => 'required|date|before_or_equal:today',
            'project_id' => 'nullable|exists:projects,id',
            'id_number' => 'nullable|max:20|required_with:id_consent',
            'id_consent' => 'nullable|boolean',
        ], [], [
            'benefited_on' => 'Date of benefit', 'id_number' => 'ID for follow-up', 'project_id' => 'Project',
        ]);

        // Without consent the ID is not kept at all.
        $data['id_consent'] = $request->boolean('id_consent');
        $data['id_number'] = $data['id_consent'] ? ($data['id_number'] ?? null) : null;

        return $data;
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'created_by_ctp');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? Str::headline($this->type);
    }

    /** The ID is only held with consent — the record never shows it without that. */
    public function showsIdNumber(): bool
    {
        return $this->id_number && $this->id_consent;
    }
}
