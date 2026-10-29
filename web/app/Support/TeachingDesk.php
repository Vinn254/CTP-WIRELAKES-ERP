<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\LessonPlan;
use App\Models\ProgressEntry;
use App\Models\TimeLog;
use App\Models\Timetable;
use App\Models\Trainer;
use App\Models\UnitMarkSheet;
use App\Models\User;
use Illuminate\Support\Collection as BaseCollection;

/**
 * The papers a trainer hands in, and what an approved paper does to a trainee's record.
 *
 * One machine serves three desks: a trainer's own desk inside the office, the same desk in an E-Fundi trainer's
 * member portal, and the Training office, which fills in the same papers for a CTP learning at a partner
 * institution. Who may open which cohort is settled here once, so a cohort cannot be recorded on from a desk that
 * does not hold it.
 *
 * A trainer's own marks stay on the paper until the Academic Registrar approves it; only then are they written onto
 * each trainee's ProgressEntry, which is what the track, the attendance floor and the certificate gate already read.
 * A paper sent back for a fix therefore never touches a trainee, and a corrected paper re-approved replaces its own
 * earlier lines rather than adding a second set.
 */
class TeachingDesk
{
    /** The four papers that are forwarded, in the order a desk lists them. */
    public const PAPERS = [
        'register' => CohortSession::class,
        'marks' => UnitMarkSheet::class,
        'timetable' => Timetable::class,
        'lesson' => LessonPlan::class,
    ];

    /** @return BaseCollection<int, Trainer> */
    public static function postings(?User $user): BaseCollection
    {
        return Trainer::forAccount($user)
            ->where('status', 'approved')
            ->values();
    }

    /** The ids of the sites this account is licensed to train at. */
    public static function trainerIds(?User $user): array
    {
        return static::postings($user)->pluck('id')->all();
    }

    /** Does this desk record on cohorts it is not personally handed — i.e. the Training office itself. */
    public static function office(?User $user): bool
    {
        return $user !== null && ($user->isSuperAdmin() || Departments::canSee($user, 'training'));
    }

    /**
     * A cohort a trainer hands papers in for: their own, or any cohort the Training office records on a partner
     * institution's behalf. Anything else 404s, so no roster is reached by id.
     */
    public static function theirCohort(Cohort $cohort, ?User $user): Cohort
    {
        abort_unless(in_array((int) $cohort->trainer_id, static::trainerIds($user), true) || static::office($user), 404);

        return $cohort;
    }

    /** Who may answer a paper — the Academic Registrar's own desk, which is the Training office. */
    public static function approves(?User $user): bool
    {
        return static::office($user);
    }

    /** The paper the signed-in account stands behind, guarding against another trainer's sheet by id. */
    public static function theirPaper(object $paper, ?User $user): object
    {
        $paper->loadMissing('cohort');
        static::theirCohort($paper->cohort, $user);

        return $paper;
    }

    /** The lines still with the Registrar, counted for the sidebar badge. */
    public static function pending(): int
    {
        return collect(self::PAPERS)->sum(fn (string $class) => $class::where('status', 'submitted')->count());
    }

    /** Every paper waiting to be answered, newest first, as [paper, kind]. */
    public static function queue(?string $kind = null): BaseCollection
    {
        $rows = collect($kind ? [$kind => self::PAPERS[$kind]] : self::PAPERS)
            ->map(fn (string $class, string $key) => $class::awaiting()
                ->with(['cohort.venue:id,name,type', 'cohort.training:id,name'])
                ->latest('submitted_at')->get()
                ->map(fn (object $paper) => ['kind' => $key, 'paper' => $paper]))
            ->flatten(1);

        return $rows->sortByDesc(fn (array $row) => $row['paper']->submitted_at)->values();
    }

    /** Send a filled paper to the Registrar. Ticks and marks stay where they are until it is answered. */
    public static function forward(object $paper): string
    {
        abort_unless($paper->isEditable(), 422, $paper->statusLabel().' — only a draft or a paper sent back can be forwarded.');
        static::guardFull($paper);

        $paper->update(['status' => 'submitted', 'submitted_at' => now()]);

        return match (true) {
            $paper instanceof CohortSession => 'Attendance register for '.$paper->dateLabel().' is with the Registrar.',
            $paper instanceof UnitMarkSheet => 'The marks for '.$paper->unitLabel().' are with the Registrar.',
            $paper instanceof Timetable => 'The timetable is with the Registrar.',
            default => 'The lesson plan is with the Registrar.',
        };
    }

    /**
     * Answer a paper. Approval is the only moment anything lands on a trainee's record, and a paper sent back after
     * it was approved takes its own lines off again — a withdrawn paper cannot keep counting.
     */
    public static function decide(object $paper, bool $approve, ?string $note, User $decider): string
    {
        abort_unless(in_array($paper->status, ['submitted', 'approved'], true), 422,
            'That paper is not with the Registrar — nothing to answer.');
        abort_if($approve && $paper->status === 'approved', 422, 'This paper is already approved and counted.');

        if (! $approve) {
            $off = $paper->status === 'approved' ? static::unSettle($paper) : 0;

            $paper->update([
                'status' => 'returned', 'reviewed_by' => $decider->id, 'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            return 'Sent back to the trainer'.($note ? ': '.$note : '.')
                .($off > 0 ? ' The '.$off.' '.str('line')->plural($off).' it had written are off the trainees’ records until it comes back.' : '');
        }

        $written = static::settle($paper);

        $paper->update([
            'status' => 'approved', 'reviewed_by' => $decider->id, 'reviewed_at' => now(), 'review_note' => $note,
        ]);

        return match (true) {
            $written > 0 => 'Approved — '.$written.' '.str('record')->plural($written).' written onto the trainees’ records.',
            $paper instanceof Timetable => 'Approved — the trainees can now see this timetable.',
            default => 'Approved.',
        };
    }

    /**
     * Write an approved register or mark sheet onto each trainee's own record. Re-running it replaces the lines this
     * paper already wrote, so a corrected paper never leaves a trainee counted twice.
     *
     * @return int how many lines the paper stands behind now
     */
    public static function settle(object $paper): int
    {
        if (! $paper instanceof CohortSession && ! $paper instanceof UnitMarkSheet) {
            return 0;
        }

        $rows = [];

        if ($paper instanceof CohortSession) {
            foreach ($paper->marks()->with('enrollment.ctp')->get() as $tick) {
                $rows[] = [
                    'enrollment_id' => $tick->enrollment_id,
                    'entry_date' => $paper->held_on->toDateString(),
                    'kind' => 'attendance',
                    'present' => $tick->present ? 1 : 0,
                    'hours' => $paper->hours ?: null,
                    'note' => trim(collect([$paper->topic, $tick->note])->filter()->join(' — ')) ?: null,
                ];
            }
        } else {
            foreach ($paper->marks()->get() as $mark) {
                if ($mark->score === null) {
                    continue;
                }

                $rows[] = [
                    'enrollment_id' => $mark->enrollment_id,
                    'entry_date' => $paper->covered_on->toDateString(),
                    'kind' => $mark->kind,
                    'score' => $mark->score,
                    'note' => trim(collect([$paper->unitLabel(), $mark->note])->filter()->join(' · ')) ?: null,
                ];
            }
        }

        ProgressEntry::where('source_type', get_class($paper))->where('source_id', $paper->id)->delete();

        $enrollments = collect(array_column($rows, 'enrollment_id'))->unique();

        foreach ($rows as $i => $row) {
            $rows[$i] = $row + [
                'source_type' => get_class($paper),
                'source_id' => $paper->id,
                'recorded_by_user' => $paper->taken_by_user,
                'recorded_by_fundi' => $paper->taken_by_fundi,
            ];
            TrainingTrack::guardPeriod($paper->cohort->enrollments()->find($row['enrollment_id']), $row['entry_date']);
        }

        if ($rows) {
            ProgressEntry::insert($rows);
        }

        foreach ($enrollments as $id) {
            TrainingTrack::syncCertification($paper->cohort->enrollments()->find($id), $paper->taken_by_user);
        }

        return count($rows);
    }

    /** Take back the lines an approved paper wrote, so a paper the desk withdraws stops counting at once. */
    public static function unSettle(object $paper): int
    {
        $off = ProgressEntry::where('source_type', get_class($paper))->where('source_id', $paper->id)->delete();

        if ($off > 0) {
            $paper->cohort->enrollments()->get()->each(
                fn ($seat) => TrainingTrack::syncCertification($seat, $paper->taken_by_user));
        }

        return $off;
    }

    /** A paper cannot go in half-filled: a register needs its ticks, a mark sheet at least one mark. */
    private static function guardFull(object $paper): void
    {
        if ($paper instanceof CohortSession) {
            abort_unless($paper->marks()->exists(), 422,
                'Tick the attendance for the day before forwarding the register.');
        }

        if ($paper instanceof UnitMarkSheet) {
            abort_unless($paper->marks()->whereNotNull('score')->exists(), 422,
                'Record at least one mark on the sheet before forwarding it.');
        }

        if ($paper instanceof Timetable) {
            abort_unless($paper->slots()->exists(), 422, 'Put at least one class on the timetable before forwarding it.');
        }
    }

    /** The week a register is read on: the same month and week-of-month picker the time log uses, and the six days
     *  a class can sit on. */
    public static function week(?string $month = null, int|string|null $week = null): array
    {
        $start = TimeSheet::monday($month, $week);

        return [
            'start' => $start,
            'month' => TimeLog::monthOf($start),
            'week_no' => TimeLog::weekNumberOf($start),
            'weeks' => TimeLog::weeksOfMonth(TimeLog::monthOf($start)),
            'label' => TimeLog::weekLabel($start),
            'days' => collect(range(0, 5))->map(fn ($i) => $start->copy()->addDays($i))->values()->all(),
        ];
    }
}
