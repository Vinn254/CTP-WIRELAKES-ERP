<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    public function index()
    {
        return view('admin.staff.index', [
            'users' => User::orderByDesc('role')->orderBy('name')->get(),
            'invites' => StaffInvite::latest()->get(),
            'isSuper' => auth()->user()->role === 'superadmin',
        ]);
    }

    public function invite(Request $request)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:users,email|unique:staff_invites,email',
            'role' => 'required|in:admin,superadmin',
        ]);

        $invite = StaffInvite::create($data + [
            'token' => Str::random(48),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(14),
        ]);

        return back()->with('status', 'Invitation created for '.$invite->email.' — copy its activation link from the Invitations list below and send it.');
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

        $data = $request->validate([
            'role' => 'required|in:admin,superadmin',
            'active' => 'boolean',
        ]);

        $user->update(['role' => $data['role'], 'active' => $request->boolean('active', $user->active)]);

        return back()->with('status', $user->name.' updated.');
    }
}
