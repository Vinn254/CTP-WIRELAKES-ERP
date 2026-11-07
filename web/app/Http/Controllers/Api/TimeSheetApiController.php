<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\TimeSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The worker's own week sheet over the API — the same ruled paper MyTimeLogController fills on the website, run
 * through TimeSheet so a day written on the phone lands on the same row and the same signature the office approves.
 *
 * A trainer who is not an E-Fundi signs in with their office account and gets this sheet beside their teaching
 * papers; an E-Fundi trainer gets it beside their own member screens.
 */
class TimeSheetApiController extends Controller
{
    /** GET /api/time-sheet?month=2026-11&week=1 — the week, its days and what the office has already said. */
    public function index(Request $request): JsonResponse
    {
        $me = $this->worker($request);
        $desk = TimeSheet::desk($me, TimeSheet::monthParam($request), $request->input('week'));

        return response()->json($this->payload($desk));
    }

    /**
     * POST /api/time-sheet — {month, week, action: draft|submit, days: {date: {time_in, time_out, task}},
     * department}. The whole week is written at once, exactly as the website's Save week / Sign and send buttons do.
     */
    public function save(Request $request): JsonResponse
    {
        $me = $this->worker($request);
        $message = TimeSheet::saveWeek($me, $request, $request->input('action') === 'submit');
        $desk = TimeSheet::desk($me, TimeSheet::monthParam($request), $request->input('week'));

        return response()->json(['message' => $message] + $this->payload($desk));
    }

    /** POST /api/time-sheet/day — one ruled line, corrected in place. */
    public function saveDay(Request $request): JsonResponse
    {
        $me = $this->worker($request);
        $message = TimeSheet::saveDay($me, $request);
        $desk = TimeSheet::desk($me, TimeSheet::monthParam($request), $request->input('week'));

        return response()->json(['message' => $message] + $this->payload($desk));
    }

    private function payload(array $desk): array
    {
        $sheet = $desk['sheet'];
        $worker = $desk['worker'];

        return [
            'worker' => ['name' => $worker->name, 'initials' => TimeLog::initialsOf($worker)],
            'can_edit' => $desk['canEdit'],
            'week' => [
                'start' => $sheet['start']->toDateString(),
                'label' => $sheet['label'],
                'month' => $sheet['month'],
                'week_no' => $sheet['week_no'],
                'status' => $sheet['status'],
                'status_label' => TimeLog::STATUSES[$sheet['status']],
                'hours' => $sheet['hours'],
                'units' => $sheet['units'],
            ],
            'weeks' => collect($sheet['weeks'])->map(fn (array $row, int $i) => [
                'no' => $i + 1, 'label' => $row['label'], 'dates' => $row['dates'],
                'start' => $row['start']->toDateString(), 'end' => $row['end']->toDateString(),
            ])->values(),
            'days' => collect($sheet['days'])->map(fn (array $row) => [
                'day' => $row['day'], 'date' => $row['date'],
                'label' => $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('D d M') : $row['day'],
                'entry' => $row['row'] ? $this->entry($row['row']) : null,
            ])->values(),
            'defaults' => [
                'initials' => $desk['defaults']['initials'],
                'departments' => $desk['defaults']['departments'],
                'department' => $desk['defaults']['department'],
            ],
            'programmes' => collect($desk['trainings'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'cohorts' => collect($desk['cohorts'])->map(fn ($c) => [
                'id' => $c->id, 'label' => $c->reference.' — '.$c->courseLabel(),
            ])->values(),
            'years' => $desk['years'],
            'statuses' => TimeLog::STATUSES,
        ];
    }

    private function entry(TimeLog $row): array
    {
        return [
            'id' => $row->id,
            'date' => $row->log_date->toDateString(),
            'time_in' => $row->time_in ? substr((string) $row->time_in, 0, 5) : null,
            'time_out' => $row->time_out ? substr((string) $row->time_out, 0, 5) : null,
            'task' => $row->task,
            'hours' => $row->hours === null ? null : (float) $row->hours,
            'units' => $row->units,
            'department' => $row->department,
            'training_id' => $row->training_id,
            'cohort_id' => $row->cohort_id,
            'status' => $row->status,
            'status_label' => TimeLog::STATUSES[$row->status] ?? $row->status,
            'approval_note' => $row->approval_note,
        ];
    }

    private function worker(Request $request): \App\Models\User
    {
        $me = $request->user();
        abort_unless(TimeSheet::worker($me), 403, 'A week sheet is filled by the staff who worked it.');

        return $me;
    }
}
