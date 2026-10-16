<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 8 §11.2 — the first site visit written down: ground, access, utilities, the risks and what the manager
 * recommended. A later visit is a new assessment, so the file keeps the sequence the decisions were made in.
 */
class SiteAssessment extends Model
{
    public const RECOMMENDATIONS = [
        'proceed' => 'Proceed as designed',
        'proceed_with_changes' => 'Proceed, with design changes',
        'redesign' => 'Re-design before any work is priced',
        'hold' => 'Hold — the site is not ready',
    ];

    protected $fillable = [
        'project_id', 'visited_on', 'ground_conditions', 'access', 'utilities', 'risks',
        'findings', 'recommendation', 'photo_path', 'photo_name', 'photo_size', 'assessed_by_user',
    ];

    protected $casts = ['visited_on' => 'date'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user')->withDefault();
    }

    public function recommendationLabel(): string
    {
        return self::RECOMMENDATIONS[$this->recommendation] ?? ucfirst(str_replace('_', ' ', (string) $this->recommendation));
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'visited_on' => 'required|date',
            'ground_conditions' => 'nullable|string|max:500',
            'access' => 'nullable|string|max:500',
            'utilities' => 'nullable|string|max:500',
            'risks' => 'nullable|string|max:1000',
            'findings' => 'required|string|max:5000',
            'recommendation' => 'required|in:'.implode(',', array_keys(self::RECOMMENDATIONS)),
            'photo' => 'nullable|image|max:8192',
        ], [
            'findings.required' => 'Write what the visit found. A photograph with no reading of the site is not an assessment (§11.2).',
            'recommendation.required' => 'Say what should happen next — proceed, re-design or hold.',
        ], [
            'visited_on' => 'Date of the visit',
            'ground_conditions' => 'Ground and soil',
            'access' => 'Access for vehicles and materials',
            'utilities' => 'Water and power on site',
            'risks' => 'Risks seen',
            'findings' => 'Findings',
            'recommendation' => 'Recommendation',
            'photo' => 'Site photos',
        ]);
    }

    public function storePhoto(Request $request): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $file = $request->file('photo');

        $this->update([
            'photo_path' => $file->store('construction/'.$this->project->reference.'/assessments', 'local'),
            'photo_name' => $file->getClientOriginalName(),
            'photo_size' => $file->getSize(),
        ]);
    }
}
