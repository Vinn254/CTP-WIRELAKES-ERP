<?php

namespace App\Models;

use App\Support\Technology;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 9 –12.6 — a piece of technology work the programme delivers: a robotics build, a software project, an
 * AI model. It runs through the AI lifecycle in one status column so a robotics prototype and a deployed chatbot are
 * read the same way.
 */
class TechnologyProject extends Model
{
    protected $table = 'technology_projects';

    protected $fillable = [
        'reference', 'category', 'title', 'summary', 'problem', 'technology', 'team_size',
        'mentor_fundi_id', 'community_id', 'project_id', 'status', 'progress_percent', 'created_by_user',
    ];

    protected $casts = ['progress_percent' => 'integer', 'team_size' => 'integer'];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'mentor_fundi_id')->withDefault();
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class)->withDefault();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withDefault();
    }

    public function statusLabel(): string
    {
        return Technology::PROJECT_STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function categoryLabel(): string
    {
        return Technology::CATEGORIES[$this->category]['label'] ?? ucfirst((string) $this->category);
    }

    public static function nextReference(): string
    {
        $prefix = 'TPR-'.now()->year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
