<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Models\Trainer;
use App\Support\TimeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The trainer week sheet, as the office sees it (§7.8, and the paper the programme signs by hand).
 *
 * A trainer writes one line per day and signs the week; this desk reads the same rows and answers the whole week in
 * one decision — approved, or returned naming what must be corrected. Human Resources is given a copy of both
 * screens and reads them without a say, because their proof of work is this sheet. The paper and the record are one
 * document: nothing here is written twice.
 */
class TimeLogController extends Controller
{
    /** Every week a trainer has filled, newest first, with what is waiting on this desk counted up. */
    public function index(Request $request)
    {
        $all = TimeSheet::weeksFor();
        $status = $request->input('status', 'all');

        return view('admin.timelogs.index', [
            'weeks' => $status === 'all' ? $all : $all->where('status', $status)->values(),
            'status' => $status,
            'statuses' => TimeLog::STATUSES,
            'counts' => $all->countBy('status'),
            'hours' => round($all->sum('hours'), 2),
        ]);
    }

    /** One trainer's week, printed exactly as the ruled sheet reads. */
    public function week(Request $request, Trainer $trainer, string $week)
    {
        $sheet = TimeSheet::sheet($trainer, $week);

        return view('admin.timelogs.week', [
            'sheet' => $sheet,
            'trainer' => $trainer,
            'week' => $sheet['start'],
            'back' => $sheet['start']->copy()->subWeek()->toDateString(),
            'onward' => $sheet['start']->copy()->addWeek()->toDateString(),
            'today' => TimeLog::weekStartOf(today())->toDateString(),
        ]);
    }

    /** The Training desk signs the week off, or sends it back with the correction written on it. */
    public function decide(Request $request, Trainer $trainer, string $week)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,returned',
            'note' => 'nullable|string|max:500|required_if:decision,returned',
        ], [], ['note' => 'What the trainer must correct', 'decision' => 'Decision']);

        $week = $this->Monday($week)->toDateString();

        return redirect()
            ->route('admin.timelogs.week', [$trainer, $week])
            ->with('status', TimeSheet::decide($trainer, $week, $data['decision'] === 'approved', $data['note'] ?? null));
    }

    /** The week a sheet belongs to, always the Monday it starts on. */
    private function Monday(string $week): Carbon
    {
        return TimeLog::weekStartOf($week);
    }
}
