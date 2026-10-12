<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InviteController extends Controller
{
    public function show(string $token)
    {
        $invite = StaffInvite::where('token', $token)->firstOrFail();

        return view('auth.invite', ['invite' => $invite->isUsable() ? $invite : null]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = StaffInvite::where('token', $token)->firstOrFail();
        abort_unless($invite->isUsable(), 403, 'This invitation is no longer valid.');

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $invite->name,
            'email' => $invite->email,
            'password' => $data['password'],
            'role' => $invite->role,
            'active' => true,
        ]);

        $invite->update(['status' => 'accepted', 'accepted_user_id' => $user->id]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('admin.departments')->with('status', 'Welcome aboard, '.$user->name.'! Your staff account is ready.');
    }
}
