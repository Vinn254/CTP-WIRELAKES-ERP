<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One record of what happened to a trainee on a day — a mark, a session, a score or a note.
 * A trainer marks it, or the office marks it on a partner school's behalf; both read the same here.
 */
class ProgressEntry extends Model
{
    public const KINDS = [
        'attendance' => 'Attendance mark',
        'theory' => 'Theory session',
        'practical' => 'Practical session',
        'assessment' => 'Assessment',
        'note' => 'Note on progress',
    ];

    protected $fillable = [
        'enrollment_id', 'entry_date', 'kind', 'present', 'score', 'hours', 'note',
        'recorded_by_user', 'recorded_by_fundi',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'present' => 'boolean',
        'score' => 'float',
        'hours' => 'integer',
    ];

    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'kind' => 'required|in:'.implode(',', array_keys(self::KINDS)),
            'entry_date' => 'required|date|before_or_equal:today',
            'present' => 'nullable|in:0,1',
            'score' => 'nullable|numeric|min:0|max:100',
            'hours' => 'nullable|integer|min:0|max:400',
            'note' => 'nullable|string|max:1000',
        ], [], [
            'entry_date' => 'Date', 'kind' => 'What happened', 'score' => 'Score out of 100',
        ]);

        // An attendance mark is only useful if it says whether they came; anything else keeps no flag.
        $data['present'] = $data['kind'] === 'attendance'
            ? ($request->boolean('present') ? 1 : 0)
            : null;

        return $data;
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user');
    }

    public function trainerFundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'recorded_by_fundi');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline($this->kind);
    }

    /** Which part of the track this line feeds and what that part is worth, so the record shows the flow and not bare marks. */
    public function countsToward(): string
    {
        $weight = Enrollment::TRACK_WEIGHTS[$this->kind] ?? null;

        return $weight === null
            ? 'not counted — kept on the record'
            : Str::headline($this->kind).' · '.$weight.'% of the track';
    }

    /** Who put the line in — a trainer's own mark or the office recording a school's report. */
    public function recorderLabel(): string
    {
        if ($this->trainerFundi) {
            return 'Trainer '.$this->trainerFundi->full_name;
        }

        if (! $this->staffUser) {
            return 'Recorded by the system';
        }

        return Trainer::trainsFrom($this->recorded_by_user)
            ? 'Trainer '.$this->staffUser->name
            : $this->staffUser->name.' (office)';
    }

    /** The short line shown on a trainee's timeline. */
    public function resultLabel(): string
    {
        if ($this->kind === 'attendance') {
            return $this->present ? 'Present' : 'Absent';
        }

        if ($this->kind === 'assessment') {
            return $this->score !== null ? 'Scored '.$this->score.'/100' : 'Assessment recorded';
        }

        if (in_array($this->kind, ['theory', 'practical'], true)) {
            return collect([
                $this->score !== null ? $this->score.'/100' : null,
                $this->hours ? $this->hours.'h covered' : null,
            ])->filter()->join(' · ');
        }

        return '';
    }
}
