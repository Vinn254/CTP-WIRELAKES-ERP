<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One class sitting inside an approved week: a day, a time, a unit.
 */
class TimetableSlot extends Model
{
    public const KINDS = [
        'theory' => 'Theory',
        'practical' => 'Practical',
        'assessment' => 'Assessment',
    ];

    protected $fillable = [
        'timetable_id', 'day', 'starts_at', 'ends_at', 'training_module_id', 'kind', 'room',
    ];

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    /** The week as posted: rows keyed day/time/unit, blank rows dropped, an incomplete row refused by name. */
    public static function rowsFrom(Request $request): array
    {
        $request->validate([
            'slots' => 'required|array',
            'slots.*.day' => 'nullable|in:'.implode(',', array_keys(Timetable::DAYS)),
            'slots.*.starts_at' => 'nullable|date_format:H:i',
            'slots.*.ends_at' => 'nullable|date_format:H:i',
            'slots.*.training_module_id' => 'nullable|exists:training_modules,id',
            'slots.*.kind' => 'nullable|in:'.implode(',', array_keys(self::KINDS)),
            'slots.*.room' => 'nullable|string|max:120',
        ], [
            'slots.*.day' => 'Pick a day of the week.',
            'slots.*.starts_at' => 'Give the class a start time, 24-hour.',
            'slots.*.ends_at' => 'Give the class an end time, 24-hour.',
        ], [
            'slots.*.day' => 'Day', 'slots.*.starts_at' => 'Starts', 'slots.*.ends_at' => 'Ends',
            'slots.*.training_module_id' => 'Unit', 'slots.*.kind' => 'Class', 'slots.*.room' => 'Room',
        ]);

        $rows = collect($request->input('slots'))
            ->filter(fn (?array $row) => filled($row))
            ->reject(fn (array $row) => blank($row['day'] ?? null) && blank($row['starts_at'] ?? null)
                && blank($row['ends_at'] ?? null))
            ->values();

        $rows->each(function (array $row, int $i) {
            foreach (['day' => 'a day', 'starts_at' => 'a start time', 'ends_at' => 'an end time'] as $field => $needs) {
                if (blank($row[$field] ?? null)) {
                    abort(422, 'Class '.($i + 1).' on the timetable is missing '.$needs.'.');
                }
            }

            if ($row['ends_at'] <= $row['starts_at']) {
                abort(422, 'Class '.($i + 1).' ends before it starts — an end time must come after the start.');
            }
        });

        return $rows->map(fn (array $row) => [
            'day' => $row['day'],
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'training_module_id' => $row['training_module_id'] ?: null,
            'kind' => $row['kind'] ?? 'theory',
            'room' => $row['room'] ?? null,
        ])->all();
    }

    public function dayLabel(): string
    {
        return Timetable::DAYS[$this->day] ?? Str::headline((string) $this->day);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline((string) $this->kind);
    }

    /** 09:00 – 11:00, printed the same on the trainer's desk and the trainee's screen. */
    public function timeLabel(): string
    {
        return substr((string) $this->starts_at, 0, 5).' – '.substr((string) $this->ends_at, 0, 5);
    }
}
