<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInviteEmail;
use App\Models\StaffInvite;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use App\Support\Departments;
use App\Support\Roles;
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
            'roles' => Roles::all(),
            'isSuper' => auth()->user()->role === 'superadmin',
        ]);
    }

    public function invite(Request $request)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403);

        $slugs = implode(',', array_column(Departments::live(), 'slug'));
        $roleSlugs = implode(',', array_keys(Roles::all()));

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:users,email|unique:staff_invites,email',
            'role' => 'required|in:admin,superadmin',
            'roles' => 'nullable|array',
            'roles.*' => 'in:'.$roleSlugs,
            'departments' => 'nullable|array',
            'departments.*' => 'in:'.$slugs,
        ]);

        // A super admin opens every department, so nothing is scoped on their invitation. An admin is granted the
        // departments their job titles open, plus any the office ticks by hand on top.
        [$roles, $departments] = $data['role'] === 'superadmin'
            ? [[], []]
            : $this->grant($request->input('roles', []), $request->input('departments', []));

        if ($data['role'] === 'admin' && $departments === [] && ! Roles::grants($roles)) {
            return back()->withInput()->with('error',
                'Give this person at least one role or department — an admin with neither opens nothing.');
        }

        $invite = StaffInvite::create([
            'name' => $data['name'],
            'email' => Accounts::email($data['email']),
            'role' => $data['role'],
            'roles' => StaffInvite::list($roles),
            'department' => StaffInvite::list($departments),
            'token' => Str::random(48),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(14),
        ]);

        Audit::auth('created', 'Invitation raised for '.$invite->email.' ('.$invite->role
            .($invite->roles ? ', '.$invite->roleLabels() : '').') by '.auth()->user()->name.'.', auth()->user());

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
        $roleSlugs = implode(',', array_keys(Roles::all()));

        $data = $request->validate([
            'role' => 'required|in:admin,superadmin',
            'active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'in:'.$roleSlugs,
            'departments' => 'nullable|array',
            'departments.*' => 'in:'.$slugs,
        ]);

        // A super admin opens every department; an admin opens the ones their roles grant, plus any ticked by hand.
        [$roles, $departments] = $data['role'] === 'superadmin'
            ? [null, null]
            : $this->grant($request->input('roles', []), $request->input('departments', []));

        // These columns decide what a person can do in the system, so they are written directly, never filled
        // from a request array by name.
        $user->forceFill([
            'role' => $data['role'],
            'active' => $request->boolean('active', $user->active),
            'roles' => $roles,
            'departments' => $departments,
        ])->save();

        Audit::auth('updated', $user->name.' holds '.($roles ? Roles::labelsFor($user) : 'no role').
            ' by '.auth()->user()->name.'.', $user);

        return back()->with('status', $user->name.' updated.');
    }

    /**
     * Turn ticked job titles into the departments they open, merged with anything the office ticks by hand.
     * Dropping a department a role still grants means unticking the role, which keeps the two never in conflict.
     *
     * @return array{0: array<int, string>, 1: array<int, string>} the role slugs and the department slugs they grant
     */
    private function grant(array $roleSlugs, array $departments): array
    {
        $live = array_column(Departments::live(), 'slug');
        $known = array_values(array_filter((array) $roleSlugs, fn ($slug) => isset(Roles::all()[$slug])));

        $granted = array_values(array_unique(array_merge(
            Roles::departmentsFor($known),
            array_intersect(array_filter((array) $departments), $live)
        )));

        return [$known, $granted];
    }
}
