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
            $me, $request->input('month'), $request->input('week')
        ));
    }

    public function saveDay(Request $request)
    {
        $me = $this->worker($request);
        TimeSheet::saveDay($me, $request);

        return $this->back($request, 'Day written to the sheet.');
    }

    /** Send the whole week to the office — after that the days are locked until it answers. */
    public function sign(Request $request)
    {
        $me = $this->worker($request);
        $week = TimeSheet::monday($request->input('month'), $request->input('week'))->toDateString();

        return $this->back($request, TimeSheet::sign($me, $week));
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
