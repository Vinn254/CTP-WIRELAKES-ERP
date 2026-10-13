<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** A CTP's seat in a cohort — the seat (state) and how far through training they are (stage) are separate. */
class Enrollment extends Model
{
    /** Below this, a trainee cannot be certified (§7.5 step 2). */
    public const ATTENDANCE_MINIMUM = 80;

    public const STATES = [
        'pending' => 'Awaiting the office',
        'approved' => 'Given a seat',
        'declined' => 'Declined',
        'withdrawn' => 'Withdrawn',
    ];

    public const STAGES = [
        'enrolled' => 'Enrolled',
        'theory' => 'Theory',
        'practical' => 'Practical',
        'assessment' => 'Assessment',
        'completed' => 'Completed',
        'failed' => 'Not completed',
    ];

    protected $fillable = [
        'cohort_id', 'ctp_id', 'state', 'stage', 'source', 'note', 'reviewed_by', 'approved_on',
    ];

    protected $casts = ['approved_on' => 'date'];

    /** The form every seat-taking screen uses — the CTP's application and the office's enrolment. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'note' => 'nullable|string|max:1000',
        ], [], ['note' => 'Reason']);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function ctp(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'ctp_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class)->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function certification(): HasOne
    {
        return $this->hasOne(Certification::class);
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state] ?? Str::headline($this->state);
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? Str::headline($this->stage);
    }

    /** Attendance as a percentage of the days someone marked — null until a mark exists. */
    public function attendancePct(): ?float
    {
        $marks = $this->entries()->where('kind', 'attendance')->whereNotNull('present')->get();

        return $marks->count()
            ? round($marks->where('present', true)->count() / $marks->count() * 100, 1)
            : null;
    }

    public function isBelowAttendance(): bool
    {
        $pct = $this->attendancePct();

        return $pct !== null && $pct < self::ATTENDANCE_MINIMUM;
    }

    /** The mean of the scores recorded so far, split by theory and practical. */
    public function avgScore(string $kind): ?float
    {
        $scores = $this->entries()->where('kind', $kind)->whereNotNull('score')->pluck('score');

        return $scores->count() ? round($scores->avg(), 1) : null;
    }

    /** Can a certificate be recommended for this seat — trained, not already put forward. */
    public function canBeCertified(): bool
    {
        return $this->state === 'approved'
            && in_array($this->stage, ['assessment', 'completed'], true)
            && ! $this->certification;
    }
}
