<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountInvite;
use App\Models\EmailOtp;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Approved CTPs and E-Fundis set their own portal password through a one-off link, the same way staff accept
 * invitations. The account stays asleep until the emailed code proves the address is theirs.
 */
class ActivateController extends Controller
{
    public function show(string $token)
    {
        $invite = AccountInvite::where('token', $token)->firstOrFail();

        return view('auth.activate', [
            'invite' => $invite->isUsable() ? $invite : null,
            'minutes' => (int) config('account.otp.minutes'),
        ]);
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

        // Their own registration is not a clash; a second account anywhere else is.
        Accounts::guard(['email' => $invite->email], $member);

        $user = User::create([
            'name' => $invite->name,
            'email' => $invite->email,
            'password' => $data['password'],
        ]);

        $user->forceFill(['role' => $invite->member_type, 'active' => false])->save();

        $member->update(['user_id' => $user->id]);
        $invite->update(['status' => 'accepted', 'accepted_user_id' => $user->id]);

        if (! EmailOtp::start($user, $user->email, 'activate', $request, route('portal.dashboard'),
            'Welcome, '.$user->name.'! Your email is confirmed — your ID is '.($member->ef_code ?? $member->ctp_code).'.')) {
            return back()->withErrors(['email' => 'We have just emailed a code to '.$user->email.'. Wait a minute, then check your inbox.']);
        }

        Audit::auth('account_created', 'Member account created for '.$user->email.' ('.$invite->member_type.'), waiting to confirm the email.', $user);

        return redirect()->route('otp.verify');
    }
}
