<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInviteEmail;
use App\Models\StaffInvite;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use App\Support\Departments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class StaffController extends Controller
{
    public function index()
    {
        return view('admin.staff.index', [
            'users' => User::whereIn('role', ['admin', 'superadmin'])->orderByDesc('role')->orderBy('name')->get(),
            'invites' => StaffInvite::latest()->get(),
            'liveDepartments' => Departments::live(),
            'isSuper' => auth()->user()->role === 'superadmin',
        ]);
    }

    public function invite(Request $request)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403);

        $slugs = implode(',', array_column(Departments::live(), 'slug'));

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:users,email|unique:staff_invites,email',
            'role' => 'required|in:admin,superadmin',
            'department' => 'nullable|required_unless:role,superadmin|in:'.$slugs,
        ], [
            'department.required_unless' => 'Choose the department this admin will work in — a super admin opens every one.',
        ]);

        if ($data['role'] === 'superadmin') {
            $data['department'] = null;
        }

        $invite = StaffInvite::create($data + [
            'email' => Accounts::email($data['email']),
            'token' => Str::random(48),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(14),
        ]);

        Audit::auth('created', 'Invitation raised for '.$invite->email.' ('.$invite->role
            .($invite->department ? ', '.$invite->department : '').') by '.auth()->user()->name.'.', auth()->user());

        // The link stays on this screen whatever the mail server answers, so a failed email never loses the invite.
        try {
            Mail::to($invite->email)->send(new StaffInviteEmail($invite));
        } catch (Throwable $e) {
            return back()->with('error', 'The invitation is saved, but its email did not go out: '.$e->getMessage()
                .' — copy the activation link from the Invitations list below and send it yourself.');
        }

        return back()->with('status', 'Invitation created and emailed to '.$invite->email
            .'. Its activation link is also in the Invitations list below.');
    }

    public function revoke(StaffInvite $invite)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403);
        $invite->update(['status' => 'revoked']);

        return back()->with('status', 'Invitation revoked.');
    }

    public function updateRole(Request $request, User $user)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403);
        abort_if($user->id === auth()->id(), 400, 'You cannot change your own role.');

        $slugs = implode(',', array_column(Departments::live(), 'slug'));

        $data = $request->validate([
            'role' => 'required|in:admin,superadmin',
            'active' => 'boolean',
            'departments' => 'nullable|array',
            'departments.*' => 'in:'.$slugs,
        ]);

        // A super admin opens every department; an admin opens only the ones ticked here.
        $departments = $data['role'] === 'superadmin'
            ? null
            : array_values(array_unique(array_filter((array) $request->input('departments', []))));

        // These three columns are what a person can do in the system, so they are written directly, never filled
        // from a request array by name.
        $user->forceFill([
            'role' => $data['role'],
            'active' => $request->boolean('active', $user->active),
            'departments' => $departments,
        ])->save();

        return back()->with('status', $user->name.' updated.');
    }
}
