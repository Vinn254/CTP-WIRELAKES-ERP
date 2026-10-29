<?php

namespace App\Models;

use App\Models\Concerns\ForwardedPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * The teaching plan a trainer writes for a cohort's whole period — lesson 1 to the last lesson — and forwards to
 * the Registrar for approval.
 *
 * Each lesson is a LessonLine carrying what is taught, its summary and content. The plan is one paper per cohort so
 * a term is read as one run of lessons rather than a stack of separate sheets.
 */
class LessonPlan extends Model
{
    use ForwardedPaper;

    protected $fillable = [
        'cohort_id', 'trainer_id', 'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'note',
        'taken_by_user', 'taken_by_fundi',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'trainer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LessonLine::class, 'lesson_plan_id')->orderBy('position');
    }

    /** The units the cohort's training lists, so a lesson is planned against a real one and not invented. */
    public function units(): Collection
    {
        return $this->cohort?->training?->modules ?? collect();
    }

    public function lessonCount(): int
    {
        return $this->relationLoaded('lines') ? $this->lines->count() : $this->lines()->count();
    }
}
