<?php

namespace App\Support;

use App\Models\TimeLog;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The week sheet, and the three desks that touch it. A worker fills one line per day and signs the week; Human
 * Resources approves it, because the same rows are the proof of work before pay; Finance reads every approved week
 * for the same reason. Every staff account fills its own sheet — whichever department the person sits in — and the
 * week is chosen by month and by which week of that month it is. Nothing here is a second copy of the sheet: each
 * desk reads the same time_logs rows.
 */
class TimeSheet
{
    /** The first year a sheet can be picked in — the year the programme started keeping them. */
    public const FIRST_YEAR = 2026;

    /**
     * Who fills a week: every office account, whichever department it sits in, plus an E-Fundi the programme has
     * posted to a site. A CTP mobilises; they are not on pay and so have no week sheet.
     */
    public static function worker(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, ['superadmin', 'admin'], true)
            || Trainer::approved()->where('user_id', $user->id)->exists()
            || Trainer::whereHas('fundi', fn ($q) => $q->where('user_id', $user->id))->exists();
    }

    /** Who answers a signed week: Human Resources for every person, because the sheet is the proof pay is built on,
     *  and the executive for all of it. Nobody signs off the week they themselves worked. */
    public static function decides(User $worker, ?User $decider = null): bool
    {
        $decider ??= auth()->user();

        if (! $decider || $decider->id === $worker->id) {
            return false;
        }

        return static::keeps($decider);
    }

    /** Who keeps the whole register: Human Resources, which answers the weeks, and the executive. */
    public static function keeps(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && ($user->isSuperAdmin() || Departments::canSee($user, 'hr'));
    }

    /** Finance is paid by these sheets, so it reads every week — but only the ones Human Resources has approved. */
    public static function paysFor(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && ! static::keeps($user) && Departments::canSee($user, 'finance');
    }

    /** Whose weeks a desk may read at all: HR and the executive every department, Finance the weeks Human Resources
     *  has approved, and every other desk only its own people — plus anyone's own sheet. */
    public static function visibleTo(Builder $query, ?User $viewer = null): Builder
    {
        if (! $viewer || static::keeps($viewer)) {
            return $query;
        }

        if (static::paysFor($viewer)) {
            return $query->where(fn ($w) => $w->where('status', 'approved')->orWhere('user_id', $viewer->id));
        }

        $slugs = Departments::visibleSlugs($viewer);

        return $query->where(fn ($w) => $w->where('user_id', $viewer->id)
            ->orWhereHas('user', fn ($u) => $u->where(function ($any) use ($slugs) {
                foreach ($slugs as $slug) {
                    $any->orWhereJsonContains('departments', $slug);
                }
            })));
    }

    /** Weeks still signed and waiting on Human Resources, counted only for the people this desk may read. */
    public static function waitingFor(?User $viewer = null): int
    {
        return static::visibleTo(TimeLog::query()->where('status', 'submitted'), $viewer)
            ->distinct()->count(['user_id', 'week_start']);
    }

    /** Whose weeks a desk may open: the same rule, asked of one person's sheet. */
    public static function opens(User $worker, ?Carbon $week = null, ?User $viewer = null): bool
    {
        $viewer ??= auth()->user();

        if (! $viewer) {
            return false;
        }

        if ($viewer->id === $worker->id || static::keeps($viewer)) {
            return true;
        }

        if (static::paysFor($viewer)) {
            return ! TimeLog::query()->where('user_id', $worker->id)->where('status', '!=', 'approved')
                ->when($week, fn ($q) => $q->where('week_start', $week->toDateString()))->exists();
        }

        return array_intersect(Departments::visibleSlugs($viewer), $worker->departmentSlugs()) !== [];
    }

    /** The sites this person teaches, when they teach — the programmes a day can be logged against. */
    public static function postings(User $worker): Collection
    {
        return Trainer::forAccount($worker)->load(['trainings:id,name', 'cohorts' => fn ($q) => $q
            ->where('status', '!=', 'draft')->orderByDesc('starts_on')]);
    }

    /** The week the request asks for: a month (YYYY-MM) and which week of it, Monday to Friday. With neither, the
     *  worker is shown the week today falls in. */
    public static function monday(?string $month = null, int|string|null $week = null): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $weeks = TimeLog::weeksOfMonth($month);
            $index = max(1, min((int) ($week ?: 1), max(count($weeks), 1))) - 1;

            return ($weeks[$index]['start'] ?? TimeLog::weekStartOf(now()))->copy();
        }

        return TimeLog::weekStartOf(Carbon::now());
    }

    /**
     * The month a picker asked for, as YYYY-MM. The picker is a calendar month and a year on their own; a link that
     * already carries both is taken as it stands.
     */
    public static function monthParam(Request $request): ?string
    {
        $asked = (string) $request->input('month', '');

        if (preg_match('/^\d{4}-\d{2}$/', $asked)) {
            return $asked;
        }

        $year = (int) $request->input('year', 0);
        $number = (int) $asked;

        return $year >= 2000 && $number >= 1 && $number <= 12 ? sprintf('%04d-%02d', $year, $number) : null;
    }

    /** The sheet for one person's week: the five ruled days, each carrying its row or nothing, plus the totals. */
    public static function sheet(User $worker, Carbon|string $weekStart): array
    {
        $weekStart = TimeLog::weekStartOf($weekStart);
        $rows = TimeLog::forWeek($worker->id, $weekStart);
        $month = TimeLog::monthOf($weekStart);

        $days = collect(range(0, 4))->map(function ($i) use ($weekStart, $rows) {
            $date = $weekStart->copy()->addDays($i)->toDateString();

            return ['day' => TimeLog::DAYS[$i], 'date' => $date, 'row' => $rows->get($date)];
        });

        $last = $weekStart->copy()->addDays(4)->toDateString();
        $extra = $rows->reject(fn ($r) => $r->log_date->toDateString() <= $last
            && $r->log_date->toDateString() >= $weekStart->toDateString())->values();

        return [
            'worker' => $worker,
            'start' => $weekStart,
            'month' => $month,
            'week_no' => TimeLog::weekNumberOf($weekStart),
            'weeks' => TimeLog::weeksOfMonth($month),
            'label' => TimeLog::weekLabel($weekStart),
            'days' => $days->concat($extra->map(fn ($r) => ['day' => $r->dayLabel(), 'date' => $r->log_date->toDateString(), 'row' => $r]))->values()->all(),
            'logs' => $rows->values(),
            'hours' => round($rows->sum(fn ($r) => (float) $r->hours), 2),
            'units' => (int) $rows->sum('units'),
            'status' => static::weekStatus($rows),
        ];
    }

    /**
     * One person's week laid out as the ruled paper the office signs by hand: the logo at the head, the days in the
     * order the sheet prints them, the totals, and the two signature boxes. Opened on screen, or saved as a file
     * when the url carries ?download=1 — the same page either way, so a downloaded sheet still prints.
     */
    public static function paper(Request $request, User $worker, Carbon|string $weekStart, string $back, string $backLabel)
    {
        $sheet = static::sheet($worker, $weekStart);

        return Documents::page($request, view('admin.timelogs.document', [
            'sheet' => $sheet,
            'worker' => $worker,
            'week' => $sheet['start'],
            'back' => $back,
            'backLabel' => $backLabel,
        ])->render(), 'Time log '.$worker->name.' '.TimeLog::weekLabel($sheet['start']));
    }

    /** A week takes the status of its rows: signed once the last day is in, and back when one row is returned. */
    public static function weekStatus(Collection $rows): string
    {
        return match (true) {
            $rows->isEmpty() => 'draft',
            $rows->contains('status', 'returned') => 'returned',
            $rows->contains('status', 'draft') => 'draft',
            $rows->contains('status', 'submitted') => 'submitted',
            default => 'approved',
        };
    }

    /** Write one day of the week. A day already on the sheet is corrected in place, never added twice. */
    public static function saveDay(User $worker, Request $request): string
    {
        static::putDay($worker, TimeLog::dataFrom($request), $request->input('department'));

        return 'Day written to the sheet.';
    }

    /**
     * One day kept on the sheet. The row for that date is corrected in place, the initials and the department are
     * taken from the account standing at the screen, and the day always comes back to a draft: a week only leaves the
     * worker's desk when they send it, and nothing is written into a week already with the office — not even a day
     * that was left out of it.
     */
    private static function putDay(User $worker, array $data, ?string $askedDepartment = null): TimeLog
    {
        $existing = TimeLog::where('user_id', $worker->id)->where('log_date', $data['log_date'])->first();

        abort_unless(in_array(static::weekStatus(TimeLog::forWeek($worker->id, $data['week_start'])), ['draft', 'returned'], true), 422,
            'This week is already with the office — it is answered there, never rewritten here.');

        if ($existing && ! in_array($existing->status, ['draft', 'returned'], true)) {
            abort(422, 'That day is already signed off — the office corrects an approved week, not the worker.');
        }

        $fields = [
            'week_start' => $data['week_start'], 'log_date' => $data['log_date'],
            'time_in' => $data['time_in'] ?? null, 'time_out' => $data['time_out'] ?? null,
            'task' => $data['task'], 'hours' => $data['hours'] ?? null, 'units' => $data['units'] ?? 0,
            'initials' => TimeLog::initialsOf($worker),
            'department' => static::departmentFor($worker, $askedDepartment, $existing?->department),
            // A week written line by line carries no programme per day, so what the day already recorded is kept.
            'training_id' => $data['training_id'] ?? $existing?->training_id,
            'cohort_id' => $data['cohort_id'] ?? $existing?->cohort_id,
            'status' => 'draft', 'approval_note' => null,
        ];

        if ($existing) {
            $existing->update($fields);

            return $existing;
        }

        $row = new TimeLog($fields);
        // The sheet is written for the account standing at the screen, never for one the form picked.
        $row->user_id = $worker->id;
        $row->save();

        return $row;
    }

    /**
     * The whole week written at once, then kept as a draft or sent to the office — the two buttons under the sheet.
     * A line left blank is left alone, so nothing on this form throws away a day already written.
     */
    public static function saveWeek(User $worker, Request $request, bool $submit): string
    {
        $start = static::monday($request->input('month'), $request->input('week'));
        $end = $start->copy()->addDays(4);
        $rows = TimeLog::forWeek($worker->id, $start);

        abort_unless(in_array(static::weekStatus($rows), ['draft', 'returned'], true), 422,
            'This week is already with the office — it is answered there, never rewritten here.');

        $written = 0;
        foreach ((array) $request->input('days', []) as $date => $day) {
            $task = trim((string) ($day['task'] ?? ''));
            $in = filled($day['time_in'] ?? null) ? $day['time_in'] : null;
            $out = filled($day['time_out'] ?? null) ? $day['time_out'] : null;

            // An untouched line is not a day — it stays off the record.
            if ($task === '' && ! $in && ! $out) {
                continue;
            }

            $when = (string) $date;
            abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $when), 422, 'A day must be picked from the week shown.');
            abort_unless($when >= $start->toDateString() && $when <= $end->toDateString(), 422,
                'Only Monday to Friday of the week you opened can be written.');
            abort_unless($when <= today()->toDateString(), 422,
                'A week sheet records days already worked — not a day in the future.');
            abort_unless($task !== '', 422, Carbon::parse($when)->format('l').' needs the tasks accomplished.');

            $hours = TimeLog::hoursBetween($in, $out);
            abort_unless(! ($in && $out) || $hours !== null, 422,
                Carbon::parse($when)->format('l').': time out must be after time in.');

            static::putDay($worker, [
                'week_start' => $start->toDateString(), 'log_date' => $when,
                'time_in' => $in, 'time_out' => $out, 'task' => $task,
                'hours' => $hours, 'units' => (int) round(($hours ?? 0) * 2),
            ], $request->input('department'));

            $written++;
        }

        if (! $submit) {
            return $written === 0
                ? 'Nothing was written — the week is kept as it stands.'
                : $written.' '.Str::plural('day', $written).' kept as a draft for the week of '
                .TimeLog::weekLabel($start).'. Sign the week when it is complete.';
        }

        abort_unless($written > 0 || $rows->isNotEmpty(), 422, 'Write at least one day before sending the week.');

        return static::sign($worker, $start);
    }

    /**
     * The department recorded on a day: the account's own. One department means no choice is asked for; several
     * mean the person picks among their own, and anything outside them is refused.
     */
    public static function departmentFor(User $worker, ?string $asked, ?string $onFile = null): string
    {
        $own = TimeLog::departmentsOf($worker);

        if (count($own) === 1) {
            return $own[0];
        }

        return match (true) {
            in_array($asked, $own, true) => $asked,
            in_array($onFile, $own, true) => $onFile,
            default => $own[0],
        };
    }

    /** The worker signs the whole week; the office then answers the week as a whole. */
    public static function sign(User $worker, Carbon|string $weekStart): string
    {
        $weekStart = TimeLog::weekStartOf($weekStart);
        $rows = TimeLog::forWeek($worker->id, $weekStart);

        abort_unless($rows->isNotEmpty(), 422, 'Write at least one day before signing the week.');
        abort_unless(in_array(static::weekStatus($rows), ['draft', 'returned'], true), 422,
            'This week is already with the office.');

        $rows->each(fn (TimeLog $row) => $row->update([
            'status' => 'submitted',
            'approval_note' => null,
            'signed_by_user' => auth()->id(),
        ]));

        return 'Week of '.TimeLog::weekLabel($weekStart).' signed and sent to the office for approval.';
    }

    /** Approve the signed week, or return it naming what is missing. */
    public static function decide(User $worker, Carbon|string $weekStart, bool $approve, ?string $note): string
    {
        $weekStart = TimeLog::weekStartOf($weekStart);
        $rows = TimeLog::forWeek($worker->id, $weekStart);

        abort_unless(static::decides($worker), 403, 'This desk does not answer that week.');
        abort_unless($rows->isNotEmpty(), 404, 'That week has no days on it.');
        abort_unless(static::weekStatus($rows) === 'submitted', 422,
            'Only a signed week can be approved or returned.');

        if (! $approve) {
            abort_unless(filled($note), 422, 'Say what must be corrected before returning the week.');
        }

        $rows->each(fn (TimeLog $row) => $row->update($approve
            ? ['status' => 'approved', 'approval_note' => $note, 'approved_by_user' => auth()->id(), 'approved_at' => now()]
            : ['status' => 'returned', 'approval_note' => $note, 'approved_by_user' => null, 'approved_at' => null]));

        return $approve
            ? 'Week of '.TimeLog::weekLabel($weekStart).' approved — '.round($rows->sum('hours'), 2).' hours signed off for pay.'
            : 'Week of '.TimeLog::weekLabel($weekStart).' returned: '.$note;
    }

    /**
     * Everything a worker's own desk shows: the chosen week, the month and week-of-month pickers, what the person
     * may log a day against, and the weeks they have already filled.
     *
     * @return array<string, mixed>
     */
    public static function desk(User $worker, ?string $month = null, int|string|null $week = null): array
    {
        $start = static::monday($month, $week);
        $sheet = static::sheet($worker, $start);
        $postings = static::postings($worker);

        return [
            'sheet' => $sheet,
            'worker' => $worker,
            'years' => static::years(),
            'trainings' => $postings->flatMap(fn (Trainer $t) => $t->trainings)->unique('id')->values(),
            'cohorts' => $postings->flatMap(fn (Trainer $t) => $t->cohorts)->unique('id')->values(),
            'defaults' => static::defaults($worker),
            'history' => static::weeksFor($worker),
            'canEdit' => in_array($sheet['status'], ['draft', 'returned'], true),
        ];
    }

    /** What the sheet records by itself: the initials this account signs with, and its own department(s). */
    public static function defaults(User $worker): array
    {
        $own = TimeLog::departmentsOf($worker);
        $last = TimeLog::where('user_id', $worker->id)->latest('log_date')->first();

        return [
            'initials' => TimeLog::initialsOf($worker),
            'departments' => $own,
            'department' => static::departmentFor($worker, null, $last?->department),
        ];
    }

    /** The years a sheet can be opened for: this one back to the first week anyone logged. */
    public static function years(): array
    {
        $first = TimeLog::query()->orderBy('week_start')->value('week_start');
        $first = $first ? (int) Carbon::parse($first)->format('Y') : (int) Carbon::now()->format('Y');

        return range(max(min($first, (int) Carbon::now()->format('Y')), self::FIRST_YEAR),
            (int) Carbon::now()->format('Y') + 1);
    }

    /** Every week anyone has filled, newest first — the office register and each worker's own history. The office
     *  copy is scoped to what the viewing desk is allowed to read. */
    public static function weeksFor(?User $worker = null, ?User $viewer = null): Collection
    {
        return static::visibleTo(
            TimeLog::query()->when($worker, fn ($q) => $q->where('user_id', $worker->id)), $viewer)
            ->with(['user:id,name,email,departments', 'training:id,name', 'cohort:id,reference'])
            ->orderByDesc('week_start')->orderBy('user_id')
            ->get()
            ->groupBy(fn (TimeLog $row) => $row->user_id.'|'.$row->week_start->toDateString())
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'worker' => $first->user,
                    'trainer' => $first->trainer,
                    'start' => $first->week_start,
                    'month' => TimeLog::monthOf($first->week_start),
                    'week_no' => TimeLog::weekNumberOf($first->week_start),
                    'label' => TimeLog::weekLabel($first->week_start),
                    'days' => $rows->count(),
                    'hours' => round($rows->sum('hours'), 2),
                    'units' => (int) $rows->sum('units'),
                    'department' => $rows->pluck('department')->filter()->first(),
                    'status' => static::weekStatus($rows),
                    'note' => $rows->pluck('approval_note')->filter()->first(),
                ];
            })
            ->values();
    }

    /** The months anyone has a sheet for, newest first — what the month pickers offer. */
    public static function months(): array
    {
        return TimeLog::query()->select('week_start')->orderByDesc('week_start')->pluck('week_start')
            ->map(fn ($d) => TimeLog::monthOf($d))->unique()->values()->all();
    }
}
