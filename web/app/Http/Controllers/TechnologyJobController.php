<?php

namespace App\Http\Controllers;

use App\Support\MemberPortal;
use App\Support\Technology;
use App\Models\TechnologyTicket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Module 9 — the fundi's own repair jobs. A ticket assigned to this worker is answered here: what was done to
 * fix it. The office confirms and closes it; nothing on this screen closes a job.
 */
class TechnologyJobController extends Controller
{
    private function member(): Model
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return $member;
    }

    public function index()
    {
        $member = $this->member();

        return view('portal.tech-jobs', MemberPortal::board($member) + [
            'jobs' => Technology::jobsFor($member),
        ]);
    }

    public function resolve(Request $request, TechnologyTicket $ticket)
    {
        $member = $this->member();

        abort_unless(Technology::jobsFor($member)->firstWhere('id', $ticket->id) !== null, 404,
            'That repair job is not one of yours.');

        Technology::resolveTicket($ticket, $request, $member->id);

        return back()->with('status', $ticket->reference.' recorded as fixed. The office confirms and closes it.');
    }
}
