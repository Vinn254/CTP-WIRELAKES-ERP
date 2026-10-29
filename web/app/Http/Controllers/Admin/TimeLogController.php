<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\TimeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The week-sheet register, as the office sees it.
 *
 * A worker writes one line per day and signs the week; this desk answers the whole week in one decision — approved,
 * or returned naming what must be corrected — because pay is built on these rows. Every other department is given a
 * copy of the same paper and reads it without a say. The printed sheet and the record are one document: nothing here
 * is written twice.
 */
class TimeLogController extends Controller
{
    /** Every week anyone has filled, newest first, with what is waiting on this desk counted up. */
    public function index(Request $request)
    {
        $all = TimeSheet::weeksFor(null, $request->user());
        $status = $request->input('status', 'all');
        // The month and the year are picked separately — the same twelve months every year, not a list that repeats.
        $month = (string) $request->input('month', '');
        $year = (string) $request->input('year', '');
        $month = preg_match('/^(0?[1-9]|1[0-2])$/', $month) ? sprintf('%02d', (int) $month) : '';
        $year = preg_match('/^\d{4}$/', $year) ? $year : '';

        $weeks = $all->when($month !== '' || $year !== '', function ($q) use ($month, $year) {
            return $q->filter(function ($w) use ($month, $year) {
                $wanted = substr($w['month'], 0, 4) === $year || $year === '';
                $number = $month === '' || substr($w['month'], 5) === $month;

                return $wanted && $number;
            })->values();
        })->when($status !== 'all', fn ($q) => $q->where('status', $status)->values());

        return view('admin.timelogs.index', [
            'weeks' => $weeks,
            'status' => $status,
            'month' => $month,
            'year' => $year,
            'years' => $all->pluck('month')->map(fn ($m) => substr($m, 0, 4))->unique()->values()
                ->sortDesc()->values()->all(),
            'statuses' => TimeLog::STATUSES,
            'counts' => $all->countBy('status'),
            'hours' => round($weeks->sum('hours'), 2),
            'units' => (int) $weeks->sum('units'),
            'people' => $weeks->pluck('worker')->filter()->unique('id')->count(),
        ]);
    }

    /** One person's week, printed exactly as the ruled sheet reads — to the desk that is allowed that person. */
    public function week(Request $request, User $user, string $week)
    {
        $monday = TimeLog::weekStartOf($week);
        abort_unless(TimeSheet::opens($user, $monday, $request->user()), 403, 'That sheet belongs to another desk.');

        $sheet = TimeSheet::sheet($user, $monday);

        return view('admin.timelogs.week', [
            'sheet' => $sheet,
            'worker' => $user,
            'week' => $sheet['start'],
            'canDecide' => TimeSheet::decides($user),
            'back' => $sheet['start']->copy()->subWeek()->toDateString(),
            'onward' => $sheet['start']->copy()->addWeek()->toDateString(),
            'today' => TimeLog::weekStartOf(today())->toDateString(),
        ]);
    }

    /** Approve the signed week, or send it back with the correction written on it. */
    public function decide(Request $request, User $user, string $week)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,returned',
            'note' => 'nullable|string|max:500|required_if:decision,returned',
        ], [], ['note' => 'What must be corrected', 'decision' => 'Decision']);

        $week = TimeLog::weekStartOf($week)->toDateString();

        return redirect()
            ->route('admin.timelogs.week', [$user, $week])
            ->with('status', TimeSheet::decide($user, $week, $data['decision'] === 'approved', $data['note'] ?? null));
    }
}
