<?php

namespace App\Models;

use App\Models\Concerns\ForwardedPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The week a cohort runs on — one timetable per cohort, edited until it is forwarded and approved.
 *
 * A trainee reads the approved one; the office and the trainer read the same rows. Slots are replaced whole on
 * every save so an edit never leaves a stale class sitting in the week.
 */
class Timetable extends Model
{
    use ForwardedPaper;

    public const DAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ];

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

    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class, 'timetable_id');
    }

    /** The week laid out day by day in class order, empty days left out. */
    public function week(): array
    {
        $byDay = $this->slots->sortBy('starts_at')->groupBy('day');

        return collect(self::DAYS)
            ->map(fn (string $label, string $day) => [
                'day' => $day,
                'label' => $label,
                'slots' => $byDay->get($day, collect())->values()->all(),
            ])
            ->reject(fn (array $row) => $row['slots'] === [])
            ->values()
            ->all();
    }
}
