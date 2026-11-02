<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttachmentLog;
use App\Models\CtpRegistration;
use App\Models\WorkOrder;
use App\Support\AttachmentDesk;
use App\Support\MemberPortal;
use App\Support\TimeSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * My attachment over the API — the placed trainee's own week log, exactly as the web screen serves it. The same
 * AttachmentDesk reads the placement and the week, and the same desk writes the days and forwards the week to the
 * Academic Registrar. An attachment is training, not a job, so nothing in here carries money.
 */
class MemberAttachmentApiController extends Controller
{
    /** GET /api/attachment?month=2026-11&week=1 — the placement, the week's sheet and the weeks already handed in. */
    public function index(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        $sheet = AttachmentDesk::sheet($ctp, $request->input('month'), $request->input('week'));

        return response()->json([
            'placement' => ($order = AttachmentDesk::placement($ctp)) ? $this->placement($order) : null,
            'week' => [
                'start' => $sheet['start']->toDateString(),
                'label' => $sheet['label'],
                'month' => $sheet['month'],
                'week_no' => $sheet['week_no'],
                'status' => $sheet['status'],
                'status_label' => AttachmentLog::STATUSES[$sheet['status']],
                'hours' => $sheet['hours'],
                'written' => $sheet['written'],
                'can_edit' => $sheet['canEdit'],
                'note' => $sheet['log']?->note,
                'review_note' => $sheet['log']?->review_note,
            ],
            'weeks' => collect($sheet['weeks'])->map(fn (array $w, int $n) => [
                'no' => $n + 1, 'label' => $w['label'], 'dates' => $w['dates'],
                'start' => $w['start']->toDateString(), 'end' => $w['end']->toDateString(),
            ])->values(),
            'days' => collect($sheet['days'])->map(fn (array $d) => $this->day($d))->values(),
            'history' => AttachmentDesk::history($ctp)->values(),
            'years' => TimeSheet::years(),
            'statuses' => AttachmentLog::STATUSES,
        ]);
    }

    /**
     * POST /api/attachment — {month, week, action: draft|submit, note, days: {date: {time_in, time_out, task,
     * initials}}}. The week the app posts is written by the same desk the website writes through, so a day left blank
     * is left alone and a week with the Registrar is refused.
     */
    public function save(Request $request): JsonResponse
    {
        $ctp = $this->ctp();

        $line = AttachmentDesk::save($ctp, $request, $request->input('action') === 'submit');
        $sheet = AttachmentDesk::sheet($ctp, $request->input('month'), $request->input('week'));

        return response()->json([
            'message' => $line,
            'week' => [
                'start' => $sheet['start']->toDateString(),
                'label' => $sheet['label'],
                'month' => $sheet['month'],
                'week_no' => $sheet['week_no'],
                'status' => $sheet['status'],
                'status_label' => AttachmentLog::STATUSES[$sheet['status']],
                'hours' => $sheet['hours'],
                'written' => $sheet['written'],
                'can_edit' => $sheet['canEdit'],
            ],
            'days' => collect($sheet['days'])->map(fn (array $d) => $this->day($d))->values(),
        ]);
    }

    private function placement(WorkOrder $order): array
    {
        $site = $order->project;

        return [
            'reference' => $order->reference,
            'site' => $site?->title,
            'place' => collect([$site?->county, $site?->ward])->filter()->join(' · '),
            'with' => $site?->hirer?->organisation,
            'placed_on' => $order->start_on?->toDateString(),
            'until' => $order->deadline?->toDateString(),
            'scope' => $order->scope,
        ];
    }

    /** One line of the week, as the app fills its row — blank clock times stay null so a day can be finished later. */
    private function day(array $d): array
    {
        $row = $d['row'];

        return [
            'date' => $d['date'],
            'day' => $d['day'],
            'past' => $d['date'] <= today()->toDateString(),
            'time_in' => $row?->time_in ? substr($row->time_in, 0, 5) : null,
            'time_out' => $row?->time_out ? substr($row->time_out, 0, 5) : null,
            'task' => $row?->task,
            'initials' => $row?->initials,
            'hours' => $row ? (float) $row->hours : null,
        ];
    }

    private function ctp(): CtpRegistration
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403,
            'An attachment week belongs to a CTP on placement.');

        return $member;
    }
}
