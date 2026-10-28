<?php

namespace App\Support;

use App\Models\TimeLog;
use App\Models\Trainer;
use App\Models\User;
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

    /**
     * Who answers a signed week: Human Resources for every person, because the sheet is the proof pay is built on,
     * and the executive for all of it. Nobody signs off the week they themselves worked.
     */
    public static function decides(User $worker, ?User $decider = null): bool
    {
        $decider ??= auth()->user();

        if (! $decider || $decider->id === $worker->id) {
            return false;
        }

        return $decider->isSuperAdmin() || in_array('hr', Departments::visibleSlugs($decider), true);
    }

    /** The sites this person teaches, when they teach — the programmes a day can be logged against. */
    public static function postings(User $worker): Collection
    {
        return Trainer::forAccount($worker)->load(['trainings:id,name', 'cohorts' => fn ($q) => $q
            ->where('status', '!=', 'draft')->orderByDesc('starts_on')]);
    }

    /**
     * The week the request asks for: a month (YYYY-MM) and which week of it, Monday to Friday. With neither, the
     * worker is shown the week today falls in.
     */
    public static function monday(?string $month = null, int|string|null $week = null): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $weeks = TimeLog::weeksOfMonth($month);
            $index = max(1, min((int) ($week ?: 1), max(count($weeks), 1))) - 1;

            return ($weeks[$index]['start'] ?? TimeLog::weekStartOf(now()))->copy();
        }

        return TimeLog::weekStartOf(Carbon::now());
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
        $data = TimeLog::dataFrom($request);
        $existing = TimeLog::where('user_id', $worker->id)->where('log_date', $data['log_date'])->first();

        if ($existing && ! in_array($existing->status, ['draft', 'returned'], true)) {
            abort(422, 'That day is already signed off — the office corrects an approved week, not the worker.');
        }

        if ($existing) {
            $existing->update($data + ['status' => 'draft', 'approval_note' => null]);

            return 'Day written to the sheet.';
        }

        $row = new TimeLog($data + ['status' => 'draft']);
        // The sheet is written for the account standing at the screen, never for one the form picked.
        $row->user_id = $worker->id;
        $row->save();

        return 'Day written to the sheet.';
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
            'months' => static::monthOptions($start),
            'trainings' => $postings->flatMap(fn (Trainer $t) => $t->trainings)->unique('id')->values(),
            'cohorts' => $postings->flatMap(fn (Trainer $t) => $t->cohorts)->unique('id')->values(),
            'defaults' => static::defaults($worker),
            'history' => static::weeksFor($worker),
            'canEdit' => in_array($sheet['status'], ['draft', 'returned'], true),
        ];
    }

    /** A day is written every week, so the sheet opens with what this person always puts in. */
    public static function defaults(User $worker): array
    {
        $last = TimeLog::where('user_id', $worker->id)->latest('log_date')->first();
        $own = Departments::visibleSlugs($worker);

        return [
            'initials' => $last?->initials ?: collect(explode(' ', trim($worker->name)))
                ->filter()->take(3)->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))->implode(''),
            'department' => $last?->department ?: (Departments::find($own[0] ?? '')['name'] ?? 'Training'),
        ];
    }

    /** The month picker: the last two years of months, newest first — a sheet is filled for a month worked. */
    public static function monthOptions(Carbon $start): array
    {
        $months = [];
        $cursor = $start->copy()->startOfMonth();

        foreach (range(0, 24) as $i) {
            $months[] = $cursor->copy()->format('Y-m');
            $cursor = $cursor->copy()->subMonth();
        }

        return $months;
    }

    /** Every week anyone has filled, newest first — the office register and each worker's own history. */
    public static function weeksFor(?User $worker = null): Collection
    {
        return TimeLog::query()
            ->when($worker, fn ($q) => $q->where('user_id', $worker->id))
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
