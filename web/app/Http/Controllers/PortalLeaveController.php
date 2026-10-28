<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Support\Audit;
use App\Support\Leave;
use Illuminate\Http\Request;

/**
 * An E-Fundi's own leave. They work for the programme — a site, a cohort, a repair — so the same seven balances
 * open for them as for an office account, and Human Resources answers the same way. A CTP is a trainee, not a
 * worker on a payroll, and is never offered this screen.
 */
class PortalLeaveController extends Controller
{
    public function index()
    {
        $user = $this->worker();

        return view('portal.leave', [
            'member' => Leave::fundiOf($user),
            'worker' => $user,
            'balances' => Leave::balances($user),
            'history' => LeaveRequest::forUser($user->id),
            'joined' => Leave::joinedOn($user),
            'months' => Leave::monthsWorked($user, now()->year),
            'applyRoute' => 'portal.leave.apply',
            'cancelRoute' => 'portal.leave.cancel',
        ]);
    }

    public function apply(Request $request)
    {
        $user = $this->worker();
        $leave = Leave::record($user, $request);

        return redirect()->route('portal.leave.index')
            ->with('status', Leave::name($leave->type).' for '.$leave->periodLabel().' ('
                .Leave::format($leave->days).' days) is with Human Resources.');
    }

    public function cancel(LeaveRequest $leave)
    {
        $this->worker();
        abort_unless($leave->user_id === auth()->id(), 403, 'That is not your leave application.');
        abort_unless($leave->status === 'pending', 400,
            'Human Resources has already answered this one — ask the desk if the plan has changed.');

        $leave->forceFill(['status' => 'cancelled'])->save();
        Audit::record($leave, 'updated', auth()->user()->name.' withdrew the application before it was answered.');

        return back()->with('status', 'Application withdrawn.');
    }

    /** Only an E-Fundi holds a post; a CTP trainee has no balance to book. */
    private function worker(): \App\Models\User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'fundi', 403,
            'Leave is booked by the people who work in the programme.');

        return $user;
    }
}
