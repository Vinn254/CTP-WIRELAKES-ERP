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
    /** Below this, a trainee cannot be certified. */
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
        'fee_status', 'fee_amount', 'fee_method', 'fee_reference', 'fee_paid_at', 'fee_recorded_by',
    ];

    protected $casts = ['approved_on' => 'date', 'fee_paid_at' => 'datetime', 'fee_amount' => 'decimal:2'];

    /** What the course fee costs for this seat — the cohort's own fee, or nothing when the cohort is free. */
    public function feeDueKsh(): float
    {
        return (float) ($this->fee_amount ?: $this->cohort->fee_ksh ?: 0);
    }

    public function feePaid(): bool
    {
        return $this->fee_status === 'paid';
    }

    public function feeLabel(): string
    {
        return match ($this->fee_status) {
            'paid' => 'Fee paid',
            'waived' => 'Fee waived',
            default => $this->feeDueKsh() > 0 ? 'Fee not paid yet' : 'No fee on this course',
        };
    }

    /** The seat fee form, validated the same way wherever it is shown. */
    public static function feeFrom(Request $request): array
    {
        return $request->validate([
            'fee_status' => 'required|in:paid,waived',
            'fee_method' => 'nullable|required_if:fee_status,paid|in:mpesa,bank,cash,cheque,other',
            'fee_amount' => 'nullable|numeric|min:0|max:999999',
            'fee_reference' => 'nullable|string|max:60',
        ], [], ['fee_method' => 'payment method', 'fee_amount' => 'amount', 'fee_reference' => 'reference']);
    }

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

    public function feeRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fee_recorded_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class)->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function certification(): HasOne
    {
        return $this->hasOne(Certification::class);
    }

    public function moduleMarks(): HasMany
    {
        return $this->hasMany(ModuleCompletion::class);
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

    /**
     * What the track is made of, and how much each part is worth. The parts always add to 100: a component the
     * programme does not need for this seat (no modules set, no school report expected) counts as met, so a
     * trainee is never held below 100% by something that does not apply to them.
     */
    public const TRACK_WEIGHTS = [
        'attendance' => 30,
        'modules' => 25,
        'theory' => 15,
        'practical' => 15,
        'assessment' => 10,
        'evidence' => 5,
    ];

    /** The mark a scored part is measured against — at or above it, the part is fully earned. */
    public const TRACK_PASS = 50;

    public function documents(): HasMany
    {
        return $this->hasMany(TrainingDocument::class)->orderByDesc('dated_on')->orderByDesc('id');
    }

    /** The modules the office put under this training, and which of them are marked on this seat.
     *  Read from the training rather than a snapshot, so a module added mid-cohort shows on every roll at once. */
    public function moduleBoard(): array
    {
        $trainingId = $this->cohort?->training_id;

        $modules = $trainingId
            ? TrainingModule::where('training_id', $trainingId)->ordered()->get()
            : collect();

        $marked = ($this->relationLoaded('moduleMarks') ? $this->moduleMarks : $this->moduleMarks()->get())
            ->keyBy('training_module_id');

        return [
            'modules' => $modules,
            'marked' => $marked,
            'outstanding' => $modules->reject(fn (TrainingModule $m) => $marked->has($m->id))->values(),
        ];
    }

    public function outstandingModules()
    {
        return $this->moduleBoard()['outstanding'];
    }

    /** Every part of the track with what it earned, so a screen can show the sum and what is missing. */
    public function trackParts(): array
    {
        $board = $this->moduleBoard();
        $attendance = $this->attendancePct();
        $theory = $this->avgScore('theory');
        $practical = $this->avgScore('practical');
        $assessment = $this->avgScore('assessment');
        $papers = $this->relationLoaded('documents') ? $this->documents->count() : $this->documents()->count();

        // A partner school teaches to its own syllabus, so its written report is the only proof the office gets.
        $needsReport = $this->cohort?->venue?->type === 'partner_school';

        $parts = [
            'attendance' => [
                'label' => 'Attendance',
                'fraction' => $attendance === null ? 0 : min($attendance / self::ATTENDANCE_MINIMUM, 1),
                'detail' => $attendance === null
                    ? 'no attendance marked yet'
                    :$attendance.'% of the days marked ('.self::ATTENDANCE_MINIMUM.'% needed)',
            ],
            'modules' => [
                'label' => 'Modules got through',
                'fraction' => $board['modules']->isEmpty() ? 1 : $board['marked']->count() / $board['modules']->count(),
                'detail' => $board['modules']->isEmpty()
                    ? 'the office has not put modules under this training — nothing to mark'
                    :$board['marked']->count().' of '.$board['modules']->count().' marked',
            ],
            'theory' => [
                'label' => 'Theory',
                'fraction' => $theory === null ? 0 : min($theory / self::TRACK_PASS, 1),
                'detail' => $theory === null ? 'no theory score recorded' : $theory.'/100 average',
            ],
            'practical' => [
                'label' => 'Practical',
                'fraction' => $practical === null ? 0 : min($practical / self::TRACK_PASS, 1),
                'detail' => $practical === null ? 'no practical score recorded' : $practical.'/100 average',
            ],
            'assessment' => [
                'label' => 'Assessment',
                'fraction' => $assessment === null ? 0 : min($assessment / self::TRACK_PASS, 1),
                'detail' => $assessment === null ? 'no assessment mark recorded' : $assessment.'/100',
            ],
            'evidence' => [
                'label' => $needsReport ? 'School report uploaded' : 'Uploaded documents',
                'fraction' => ! $needsReport || $papers > 0 ? 1 : 0,
                'detail' => $needsReport
                    ? ($papers > 0 ? $papers.' document(s) on file' : 'the partner school report is not uploaded yet')
                    :($papers > 0 ? $papers.' document(s) on file — not required at this site' : 'nothing uploaded — none needed at this site'),
            ],
        ];

        return collect($parts)->map(fn ($p, $key) => [
            'label' => $p['label'],
            'weight' => self::TRACK_WEIGHTS[$key],
            'got' => round($p['fraction'] * self::TRACK_WEIGHTS[$key], 1),
            'done' => $p['fraction'] >= 1,
            'detail' => $p['detail'],
        ])->all();
    }

    /** A seat that is not held has no track — an application is not progress. */
    public function trackPct(): float
    {
        if ($this->state !== 'approved') {
            return 0.0;
        }

        return round(collect($this->trackParts())->sum('got'), 1);
    }

    public function isTrackComplete(): bool
    {
        return $this->trackPct() >= 100;
    }

    public function trackMissing()
    {
        return collect($this->trackParts())->reject(fn ($p) => $p['done'])->values();
    }

    /** Can a certificate be recommended for this seat — trained, the whole track earned, not already put forward. */
    public function canBeCertified(): bool
    {
        return $this->state === 'approved'
            && in_array($this->stage, ['assessment', 'completed'], true)
            && $this->isTrackComplete()
            && ! $this->certification;
    }
}
