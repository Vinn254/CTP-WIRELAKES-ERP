<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInviteEmail;
use App\Models\StaffInvite;
use App\Models\Training;
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
            'users' => User::whereIn('role', ['admin', 'superadmin'])->with('trainerField:id,name')
                ->orderByDesc('role')->orderBy('name')->get(),
            'invites' => StaffInvite::latest()->with('trainerField:id,name')->get(),
            'liveDepartments' => Departments::live(),
            'roles' => Roles::all(),
            'fields' => Training::orderBy('name')->get(['id', 'name']),
            'isSuper' => auth()->user()->role === 'superadmin',
            'canManage' => $this->mayManage(),
        ]);
    }

    /** The rules a Trainer title carries: a trainer must be given a field, picked from the programme's catalogue. */
    private function rules(Request $request): array
    {
        $training = in_array('trainer', (array) $request->input('roles', []), true);

        return [
            'roles' => 'nullable|array',
            'roles.*' => 'in:'.implode(',', array_keys(Roles::all())),
            'departments' => 'nullable|array',
            'departments.*' => 'in:'.implode(',', array_column(Departments::live(), 'slug')),
            'trainer_training_id' => ($training ? 'required' : 'nullable').'|integer|exists:trainings,id',
        ];
    }

    private function field(Request $request, array $roles): ?int
    {
        return in_array('trainer', $roles, true) ? (int) $request->input('trainer_training_id') : null;
    }

    public function invite(Request $request)
    {
        $this->gate();

        // No account type is chosen here: every person this desk invites is an admin, and the one super admin
        // account the programme already holds is the only one that opens everything.
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:users,email|unique:staff_invites,email',
        ] + $this->rules($request), ['trainer_training_id.required' => 'Tick the field of training for this trainer.'], ['trainer_training_id' => 'Field of training']);

        [$roles, $departments] = $this->grant($request->input('roles', []), $request->input('departments', []));

        if ($departments === []) {
            return back()->withInput()->with('error',
                'Give this person at least one role or department — an admin with neither opens nothing.');
        }

        if ($this->opensEverything($departments)) {
            return back()->withInput()->with('error',
                'An admin is given the modules their work needs. Every module at once is a super admin, and the '
                .'programme already holds that account.');
        }

        $invite = StaffInvite::create([
            'name' => $data['name'],
            'email' => Accounts::email($data['email']),
            'role' => 'admin',
            'roles' => StaffInvite::list($roles),
            'trainer_training_id' => $this->field($request, $roles),
            'department' => StaffInvite::list($departments),
            'token' => Str::random(48),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(14),
        ]);

        Audit::auth('created', 'Invitation raised for '.$invite->email.' (admin'
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
        $this->gate();
        $invite->update(['status' => 'revoked']);

        return back()->with('status', 'Invitation revoked.');
    }

    public function updateRole(Request $request, User $user)
    {
        $this->gate();
        $this->guardTarget($user);

        // The account type is not part of this form. What this desk sets is the job titles and the modules they open.
        $request->validate([
            'active' => 'boolean',
        ] + $this->rules($request), ['trainer_training_id.required' => 'Tick the field of training for this trainer.'], ['trainer_training_id' => 'Field of training']);

        [$roles, $departments] = $this->grant($request->input('roles', []), $request->input('departments', []));

        if ($departments === []) {
            return back()->withInput()->with('error',
                $user->name.' would open nothing with no role and no module ticked — keep at least one.');
        }

        if ($this->opensEverything($departments)) {
            return back()->withInput()->with('error',
                $user->name.' cannot be handed every module at once — that is a super admin, and the programme '
                .'already holds that account.');
        }

        // These columns decide what a person can do in the system, so they are written directly, never filled
        // from a request array by name.
        $user->forceFill([
            'active' => $request->boolean('active', $user->active),
            'roles' => $roles,
            'departments' => $departments,
            'trainer_training_id' => $this->field($request, $roles),
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

    /** Staff accounts are a Human Resources record, so HR runs this desk beside the executive — the careers board,
     *  the staff directory, the payroll and the leave register sit on the same desk. */
    private function mayManage(): bool
    {
        return Departments::mayInvite(auth()->user());
    }

    /** Every module at once is the super admin's run; an admin is given only what their work needs. */
    private function opensEverything(array $departments): bool
    {
        return $departments !== [] && count($departments) >= count(Departments::live());
    }

    private function gate(): void
    {
        abort_unless($this->mayManage(), 403,
            'Staff accounts and roles are kept by Human Resources — the careers board, the staff directory and the '
            .'leave register sit on the same desk.');
    }

    /**
     * This desk never touches the super admin account, and no one rewrites their own access. A second super admin
     * is not something an invitation can make, so the only way in is through the account the programme already has.
     */
    private function guardTarget(User $user): void
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot change your own account — someone else holds this desk.');

        abort_if($user->role === 'superadmin', 403,
            'The super admin account is not edited from this desk. It opens every module already, and admin accounts '
            .'are what this screen is for.');
    }
}
