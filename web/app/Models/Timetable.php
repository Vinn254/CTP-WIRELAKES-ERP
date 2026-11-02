<?php

namespace App\Models;

use App\Models\Concerns\ForwardedPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class week. Two authors read the one table: a trainer writes the week of their own cohort and forwards it to the
 * Registrar, and the Registrar writes the week a programme is meant to run on, which every trainer on that programme
 * sees. A row hangs on a cohort or on a training, never on both.
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
        'cohort_id', 'training_id', 'trainer_id', 'status', 'submitted_at', 'reviewed_by', 'reviewed_at',
        'review_note', 'note', 'taken_by_user', 'taken_by_fundi',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** The programme this week is laid out for — set on the Registrar's own week. */
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    /** The Registrar's week for a programme; a trainer's cohort row is never read here. */
    public static function forProgramme(int $trainingId): ?self
    {
        return static::where('training_id', $trainingId)->whereNull('cohort_id')
            ->with('slots.module:id,title')->latest('id')->first();
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
