<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Support\TimeSheet;
use Illuminate\Http\Request;

/**
 * A worker's own week sheet. Every office account fills it — Finance, Human Resources, Technology, whoever sits at
 * a desk in the organisation — by picking the month and which week of that month, then writing Monday to Friday and
 * signing the week for Human Resources to approve. The teaching desk route lands here too: one sheet, one place.
 */
class MyTimeLogController extends Controller
{
    public function index(Request $request)
    {
        $me = $this->worker($request);

        return view('admin.timelogs.my', TimeSheet::desk(
            $me, TimeSheet::monthParam($request), $request->input('week')
        ));
    }

    public function saveDay(Request $request)
    {
        $me = $this->worker($request);
        TimeSheet::saveDay($me, $request);

        return $this->back($request, 'Day written to the sheet.');
    }

    /** Keep the week as a draft to finish later, or write it and send it — the two buttons under the sheet. */
    public function saveWeek(Request $request)
    {
        $me = $this->worker($request);

        return $this->back($request, TimeSheet::saveWeek($me, $request, $request->input('action') === 'submit'));
    }

    /** The worker's own week as the ruled paper: printed, or downloaded and kept. */
    public function sheet(Request $request, string $week)
    {
        $me = $this->worker($request);
        $monday = TimeLog::weekStartOf($week);

        return TimeSheet::paper($request, $me, $monday,
            route('admin.mytime.index', ['month' => TimeLog::monthOf($monday), 'week' => TimeLog::weekNumberOf($monday)]),
            'Back to my time log');
    }

    private function worker(Request $request)
    {
        $me = $request->user();
        abort_unless(TimeSheet::worker($me), 403, 'A week sheet is filled by the staff who worked it.');

        return $me;
    }

    /** The day was written from a popup on a certain week — go back to that same week, not to today's. */
    private function back(Request $request, string $message)
    {
        $week = TimeSheet::monday($request->input('month'), $request->input('week'))->toDateString();

        return redirect()
            ->route('admin.mytime.index', ['month' => TimeLog::monthOf($week), 'week' => TimeLog::weekNumberOf($week)])
            ->with('status', $message);
    }
}
