<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Models\UnitGrading;
use App\Models\UnitMark;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What the marks a unit was taken on come to.
 *
 * A lecturer or trainer enters the marks and the Registrar approves the sheet; the Registrar's weights
 * ({@see UnitGrading}) then decide what each of those marks is worth, and the sum is the student's grade for that
 * unit out of 100 against the pass mark the Registrar set. Reading is always through here so the marks grid, the
 * student's record, the printed transcript, the portal and the mobile app all show one figure and never four.
 *
 * A part with no mark counts as nothing earned — a missing mark is not a free pass. A part weighted at 0 is simply
 * not used by that unit, so a practical-only course can drop theory altogether.
 */
class Grading
{
    /** How a unit is graded until the Registrar sets its own. */
    public const DEFAULTS = [
        'cat_pct' => 20,
        'assignment_pct' => 10,
        'theory_pct' => 40,
        'practical_pct' => 20,
        'attendance_pct' => 10,
        'pass_pct' => 40,
    ];

    /** The Registrar's row for a unit, or an unset one carrying the default weights. */
    public static function for(TrainingModule $unit): UnitGrading
    {
        static::$cache ??= [];
        $grading = static::$cache[$unit->id] ??= UnitGrading::firstOrNew(['training_module_id' => $unit->id]);

        return $grading->exists ? $grading : $grading->fill(static::DEFAULTS);
    }

    /** @var array<int, UnitGrading>|null */
    private static ?array $cache = null;

    /** Keep the Registrar's weights for a unit, overwriting the row that is already there. */
    public static function save(TrainingModule $unit, array $data, User $by): string
    {
        $grading = UnitGrading::firstOrNew(['training_module_id' => $unit->id]);

        $grading->fill(collect(UnitGrading::PARTS)->mapWithKeys(
            fn (string $label, string $part) => ["{$part}_pct" => (int) $data["{$part}_pct"]])->all())
            ->fill([
                'pass_pct' => $data['pass_pct'],
                'note' => $data['note'] ?? null,
                'set_by_user' => $by->id,
            ]);

        $grading->save();
        static::$cache = null;

        return $unit->title.' is now graded '.static::weightLine($grading)
            .' — a student passes the unit at '.$grading->pass_pct.'.';
    }

    /** The weights written out in one line: CAT 20 · Assignment 10 · Theory 40 · Practical 20 · Attendance 10. */
    public static function weightLine(UnitGrading $grading): string
    {
        return collect(UnitGrading::PARTS)
            ->filter(fn (string $label, string $part) => (int) $grading->{"{$part}_pct"} > 0)
            ->map(fn (string $label, string $part) => $label.' '.$grading->{"{$part}_pct"}.'%')
            ->join(' · ');
    }

    /**
     * The grade one student earned on one unit, weighed the way the Registrar set it.
     *
     * @return array{parts: array<string, array{label: string, weight: int, score: float|null, got: float}>,
     *                               pct: float, pass_pct: float, passed: bool, letter: string, remark: string,
     *                               marked: bool}
     */
    public static function compile(Enrollment $seat, TrainingModule $unit): array
    {
        return static::weigh(static::for($unit), static::marksFor($seat, $unit)->all(), $seat->attendancePct());
    }

    /**
     * The arithmetic every screen reads through: marks out of 100, the attendance the register holds, and the
     * weights set on the unit. One method so a cohort table, a transcript and a marks grid never differ.
     */
    public static function weigh(UnitGrading $grading, array $marks, ?float $attendance): array
    {
        $parts = [];

        foreach (UnitGrading::PARTS as $part => $label) {
            $weight = (int) $grading->{"{$part}_pct"};
            $score = $part === 'attendance' ? $attendance : ($marks[$part] ?? null);

            $parts[$part] = [
                'label' => $label,
                'weight' => $weight,
                'score' => $score === null ? null : round((float) $score, 1),
                'got' => $weight > 0 ? round(((float) ($score ?? 0)) * $weight / 100, 2) : 0.0,
            ];
        }

        $pct = round(array_sum(array_column($parts, 'got')), 1);
        $pass = (float) $grading->pass_pct;

        // Attendance is never a grade on its own: a unit counts as marked only once an approved mark stands behind
        // one of the parts it is weighed on.
        $marked = collect(UnitGrading::PARTS)->except('attendance')->keys()->contains(
            fn (string $part) => (int) $grading->{"{$part}_pct"} > 0 && ($marks[$part] ?? null) !== null);

        return [
            'parts' => $parts,
            'pct' => $pct,
            'pass_pct' => $pass,
            'passed' => $marked && $pct >= $pass,
            'letter' => static::letter($pct),
            'remark' => ! $marked ? 'Not marked yet' : ($pct >= $pass ? 'Pass' : 'Below the pass mark'),
            'marked' => $marked,
        ];
    }

    /**
     * A whole cohort read unit by unit: the mark each part carries on average, and what the units come to once
     * weighed. This is the table the office and the trainer read the results from.
     *
     * @param  Collection<int, Enrollment>  $seats
     * @return list<array{unit: TrainingModule, grading: UnitGrading, marks: array<string, float>,
     *                     graded: int, pct: float|null, passed: int}>
     */
    public static function cohortBoard(Cohort $cohort, Collection $seats): array
    {
        $rows = UnitMark::query()
            ->join('unit_mark_sheets as s', 's.id', '=', 'unit_marks.sheet_id')
            ->where('s.cohort_id', $cohort->id)->where('s.status', 'approved')
            ->whereIn('unit_marks.enrollment_id', $seats->pluck('id'))
            ->whereNotNull('unit_marks.score')
            ->get(['s.training_module_id', 'unit_marks.enrollment_id', 'unit_marks.kind', 'unit_marks.score']);

        $byUnit = $rows->groupBy('training_module_id')
            ->map(fn (Collection $forUnit) => $forUnit->groupBy('enrollment_id')
                ->map(fn (Collection $forSeat) => $forSeat->groupBy('kind')
                    ->map(fn (Collection $scores) => round($scores->avg('score'), 1))->all())->all());
        $board = [];

        // The units the office ticked onto this intake are the columns the table is read on.
        foreach ($cohort->unitList() as $unit) {
            $grading = static::for($unit);
            $grades = [];
            $sums = [];
            $att = [];

            foreach ($seats as $seat) {
                $marks = $byUnit->get($unit->id, [])[$seat->id] ?? [];
                $grade = static::weigh($grading, $marks, $seat->attendancePct());

                if (! $grade['marked']) {
                    continue;
                }

                $grades[] = $grade;

                if ($seat->attendancePct() !== null) {
                    $att[] = $seat->attendancePct();
                }

                foreach ($marks as $kind => $mark) {
                    $sums[$kind][] = $mark;
                }
            }

            $board[] = [
                'unit' => $unit,
                'grading' => $grading,
                'marks' => collect($sums)->map(fn (array $v) => round(array_sum($v) / count($v), 1))->all(),
                'attendance' => $att ? round(array_sum($att) / count($att), 1) : null,
                'graded' => count($grades),
                'pct' => $grades ? round(collect($grades)->avg('pct'), 1) : null,
                'passed' => collect($grades)->filter(fn (array $g) => $g['passed'])->count(),
            ];
        }

        return $board;
    }

    /**
     * The same table read across a whole programme: every unit the course teaches, the marks its sheets carry on
     * average over every intake that has run it, and what they come to on the Registrar's own weights. The Registrar
     * grades a programme, not just one class, so this is where a course is regulated term after term.
     *
     * @param  Collection<int, Enrollment>  $seats  every seat held on the programme's intakes
     * @return list<array{unit: TrainingModule, grading: UnitGrading, marks: array<string, float>,
     *                     graded: int, pct: float|null, passed: int}>
     */
    public static function programmeBoard(Training $training, Collection $seats): array
    {
        $rows = UnitMark::query()
            ->join('unit_mark_sheets as s', 's.id', '=', 'unit_marks.sheet_id')
            ->whereIn('s.cohort_id', $seats->pluck('cohort_id')->filter()->unique())
            ->whereIn('unit_marks.enrollment_id', $seats->pluck('id'))
            ->where('s.status', 'approved')
            ->whereNotNull('unit_marks.score')
            ->get(['s.training_module_id', 'unit_marks.enrollment_id', 'unit_marks.kind', 'unit_marks.score']);

        $byUnit = $rows->groupBy('training_module_id')
            ->map(fn (Collection $forUnit) => $forUnit->groupBy('enrollment_id')
                ->map(fn (Collection $forSeat) => $forSeat->groupBy('kind')
                    ->map(fn (Collection $scores) => round($scores->avg('score'), 1))->all())->all());
        $board = [];

        foreach ($training->modules as $unit) {
            $grading = static::for($unit);
            $grades = [];
            $sums = [];
            $att = [];

            foreach ($seats as $seat) {
                $marks = $byUnit->get($unit->id, [])[$seat->id] ?? [];
                $grade = static::weigh($grading, $marks, $seat->attendancePct());

                if (! $grade['marked']) {
                    continue;
                }

                $grades[] = $grade;

                if ($seat->attendancePct() !== null) {
                    $att[] = $seat->attendancePct();
                }

                foreach ($marks as $kind => $mark) {
                    $sums[$kind][] = $mark;
                }
            }

            $board[] = [
                'unit' => $unit,
                'term' => $unit->termLabel(),
                'grading' => $grading,
                'marks' => collect($sums)->map(fn (array $v) => round(array_sum($v) / count($v), 1))->all(),
                'attendance' => $att ? round(array_sum($att) / count($att), 1) : null,
                'graded' => count($grades),
                'pct' => $grades ? round(collect($grades)->avg('pct'), 1) : null,
                'passed' => collect($grades)->filter(fn (array $g) => $g['passed'])->count(),
            ];
        }

        return $board;
    }

    /** The best approved mark a student has on each kind of this unit, out of 100. */
    public static function marksFor(Enrollment $seat, TrainingModule $unit): Collection
    {
        $rows = UnitMark::query()
            ->join('unit_mark_sheets as s', 's.id', '=', 'unit_marks.sheet_id')
            ->where('s.training_module_id', $unit->id)
            ->where('s.cohort_id', $seat->cohort_id)
            ->where('s.status', 'approved')
            ->where('unit_marks.enrollment_id', $seat->id)
            ->whereNotNull('unit_marks.score')
            ->get(['unit_marks.kind', 'unit_marks.score']);

        return $rows->groupBy('kind')->map(fn (Collection $g) => round($g->avg('score'), 1));
    }

    /** Every unit a student sits in, weighed and graded — what a transcript and a portal page are built from. */
    public static function forSeat(Enrollment $seat): array
    {
        $units = $seat->unitList();

        return $units->map(fn (TrainingModule $unit) => ['unit' => $unit] + static::compile($seat, $unit))
            ->filter(fn (array $row) => $row['marked'])->values()->all();
    }

    /** The overall of a whole programme: the mean of the units the student was marked on. */
    public static function overall(array $grades): ?float
    {
        return $grades ? round(collect($grades)->avg('pct'), 1) : null;
    }

    /** The letter a mark of 0–100 is written as on a transcript. */
    public static function letter(float $pct): string
    {
        return match (true) {
            $pct >= 80 => 'A',
            $pct >= 70 => 'B',
            $pct >= 60 => 'C',
            $pct >= 50 => 'D',
            $pct >= 40 => 'E',
            default => 'F',
        };
    }
}
