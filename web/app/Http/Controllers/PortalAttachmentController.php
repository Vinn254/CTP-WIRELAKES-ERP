<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\TimeLog;
use App\Support\AttachmentDesk;
use App\Support\MemberPortal;
use App\Support\TimeSheet;
use Illuminate\Http\Request;

/**
 * My attachment — the placed trainee's own page. A CTP who has finished the course and been given a site by the
 * office logs the days they sat there and sends the week to the Academic Registrar. An attachment is not paid, so
 * nothing on this screen mentions a job, a stage or money.
 */
class PortalAttachmentController extends Controller
{
    public function index(Request $request)
    {
        $ctp = $this->ctp();

        return view('portal.attachment', MemberPortal::board($ctp) + [
            'placement' => AttachmentDesk::placement($ctp),
            'sheet' => AttachmentDesk::sheet($ctp, TimeSheet::monthParam($request), $request->input('week')),
            'history' => AttachmentDesk::history($ctp),
            'years' => TimeSheet::years(),
        ]);
    }

    /** Keep the week to finish later, or send it to the Registrar — the two buttons under the sheet. */
    public function save(Request $request)
    {
        $ctp = $this->ctp();
        $line = AttachmentDesk::save($ctp, $request, $request->input('action') === 'submit');

        $week = TimeSheet::monday($request->input('month'), $request->input('week'))->toDateString();

        return redirect()
            ->route('portal.attachment', ['month' => TimeLog::monthOf($week), 'week' => TimeLog::weekNumberOf($week)])
            ->with('status', $line);
    }

    private function ctp(): CtpRegistration
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403, 'An attachment week belongs to a CTP on placement.');

        return $member;
    }
}
