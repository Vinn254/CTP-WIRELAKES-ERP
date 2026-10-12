<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountInvite;
use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Approved CTPs and E-Fundis set their own portal password through a one-off link,
 * the same way staff accept invitations.
 */
class ActivateController extends Controller
{
    public function show(string $token)
    {
        $invite = AccountInvite::where('token', $token)->firstOrFail();

        return view('auth.activate', ['invite' => $invite->isUsable() ? $invite : null]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = AccountInvite::where('token', $token)->firstOrFail();
        abort_unless($invite->isUsable(), 403, 'This activation link is no longer valid.');

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $member = $invite->member();
        abort_if(! $member, 404, 'The member record behind this link no longer exists.');

        $user = User::create([
            'name' => $invite->name,
            'email' => $invite->email,
            'password' => $data['password'],
            'role' => $invite->member_type,
            'active' => true,
        ]);

        $member->update(['user_id' => $user->id]);
        $invite->update(['status' => 'accepted', 'accepted_user_id' => $user->id]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('status', 'Welcome, '.$user->name.'! Your member ID is '.($member->ef_code ?? $member->ctp_code).'. Sign in here any time to see projects.');
    }
}
