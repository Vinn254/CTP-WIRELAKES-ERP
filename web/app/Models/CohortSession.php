<?php

namespace App\Models;

use App\Models\Concerns\ForwardedPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One day a cohort actually sat — the attendance register a trainer ticks and forwards to the Registrar.
 *
 * The ticks themselves live on cohort_session_marks so a register can be corrected while it is still a draft; the
 * trainee's own attendance lines are only written when the Registrar approves the day.
 */
class CohortSession extends Model
{
    use ForwardedPaper;

    public const KINDS = [
        'theory' => 'Theory class',
        'practical' => 'Practical class',
        'assessment' => 'Assessment',
    ];

    protected $fillable = [
        'cohort_id', 'trainer_id', 'held_on', 'kind', 'topic', 'hours', 'sheet_path', 'sheet_name', 'note',
        'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'taken_by_user', 'taken_by_fundi',
    ];

    protected $casts = [
        'held_on' => 'date',
        'hours' => 'integer',
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

    public function marks(): HasMany
    {
        return $this->hasMany(CohortSessionMark::class, 'session_id')->orderBy('enrollment_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline((string) $this->kind);
    }

    /** The day as the paper reads it: Mon 12 Oct 2026. */
    public function dateLabel(): string
    {
        return $this->held_on?->format('D d M Y') ?: 'date not set';
    }

    public function presentCount(): int
    {
        return $this->relationLoaded('marks')
            ? $this->marks->where('present', true)->count()
            : $this->marks()->where('present', true)->count();
    }
}
