<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Support\Audit;
use App\Support\Departments;
use App\Support\Leave;
use Illuminate\Http\Request;

/**
 * Leave. The office desk (admin/leave) answers applications, and every worker's own page (admin/my-leave) books
 * them. Another department is given a read-only copy of the register for the people who hold it, so a desk can see
 * who of its own is away without anyone leaving the department they work in.
 */
class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $host = Departments::currentSlug();
        $read = Departments::hosted() && $host !== 'hr';
        $year = (int) ($request->input('year') ?: now()->year);

        $rows = LeaveRequest::with(['user:id,name,email,role,departments', 'decidedBy:id,name'])
            ->whereYear('starts_on', $year)
            ->when($read, fn ($q) => $q->whereHas('user', fn ($u) => $u->whereJsonContains('departments', $host)))
            ->when($type = $request->input('type'), fn ($q) => $q->where('type', $type))
            ->when($status = $request->input('status'), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get();

        return view('admin.leave.index', [
            'rows' => $rows,
            'waiting' => $rows->where('status', 'pending')->values(),
            'read' => $read,
            'year' => $year,
            'years' => LeaveRequest::query()->get(['starts_on'])
                ->map(fn ($r) => (int) $r->starts_on->format('Y'))->unique()->sortDesc()->values()->all()
                ?: [now()->year],
            'types' => Leave::TYPES,
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        abort_unless(Leave::decides(auth()->user()), 403,
            'Only the Human Resources desk answers a leave application.');

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:500',
        ], [], ['decision' => 'Decision', 'note' => 'Note']);

        $message = Leave::decide($leave, $data['decision'], $data['note'] ?? null, auth()->user());

        return back()->with('status', $message);
    }

    /** The person's own balances, application form and history — the same board an E-Fundi sees in the portal. */
    public function my()
    {
        $user = auth()->user();

        // A trainee being taught does not hold a post, so no balance opens; an E-Fundi books the same leave in the portal.
        abort_unless(Leave::worker($user), 403, 'Leave is booked by the people who work in the programme.');

        return view('admin.leave.my', [
            'worker' => $user,
            'balances' => Leave::balances($user),
            'history' => LeaveRequest::forUser($user->id),
            'joined' => Leave::joinedOn($user),
            'months' => Leave::monthsWorked($user, now()->year),
            'applyRoute' => 'admin.myleave.apply',
            'cancelRoute' => 'admin.myleave.cancel',
        ]);
    }

    public function apply(Request $request)
    {
        $user = auth()->user();
        abort_unless(Leave::worker($user), 403, 'Leave is booked by the people who work in the programme.');

        $leave = Leave::record($user, $request);

        return redirect()->route('admin.myleave.index')
            ->with('status', Leave::name($leave->type).' for '.$leave->periodLabel().' ('
                .Leave::format($leave->days).' days) is with Human Resources.');
    }

    /** A person withdraws their own application before the desk answers it; the row stays on the register. */
    public function cancel(LeaveRequest $leave)
    {
        abort_unless($leave->user_id === auth()->id(), 403, 'That is not your leave application.');
        abort_unless($leave->status === 'pending', 400,
            'Human Resources has already answered this one — the desk reopens it if the plan has changed.');

        $leave->forceFill(['status' => 'cancelled'])->save();
        Audit::record($leave, 'updated', auth()->user()->name.' withdrew the application before it was answered.');

        return back()->with('status', 'Application withdrawn.');
    }
}
