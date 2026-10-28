<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\StaffInvite;
use App\Models\StaffRecord;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function show(string $token)
    {
        $invite = StaffInvite::where('token', $token)->firstOrFail();

        return view('auth.invite', [
            'invite' => $invite->isUsable() ? $invite : null,
            'minutes' => (int) config('account.otp.minutes'),
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = StaffInvite::where('token', $token)->firstOrFail();
        abort_unless($invite->isUsable(), 403, 'This invitation is no longer valid.');

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // One person, one login account: an invitation cannot open a second one for an address already signed in.
        // The office decides who works in it, so this check is against the accounts, not the field registers.
        Accounts::guard(['email' => $invite->email], null, [User::class]);

        // The account is born unverified. It only wakes when the code emailed to that address is typed back.
        $user = User::create([
            'name' => $invite->name,
            'email' => $invite->email,
            'password' => $data['password'],
        ]);

        $user->forceFill([
            'role' => $invite->role,
            'active' => false,
            'roles' => $invite->roleSlugs() ?: null,
            'departments' => $invite->role === 'superadmin' ? null : ($invite->departmentSlugs() ?: null),
        ])->save();

        $invite->update(['status' => 'accepted', 'accepted_user_id' => $user->id]);

        // A person the onboarding desk already wrote up keeps that file: the record finds its new account.
        StaffRecord::claim($user);

        if (! EmailOtp::enabled()) {
            return EmailOtp::signInWithoutCode($user, $request, route('admin.departments'),
                'Welcome aboard, '.$user->name.'! Your staff account is ready.');
        }

        if (! EmailOtp::start($user, $user->email, 'activate', $request, route('admin.departments'),
            'Welcome aboard, '.$user->name.'! Your email is confirmed and your staff account is ready.')) {
            Audit::auth('otp_sent', 'Invitation for '.$user->email.' set, but a code went moments ago — nothing sent twice.');

            return back()->withErrors(['email' => 'We have just emailed a code to '.$user->email.'. Wait a minute, then check your inbox.']);
        }

        Audit::auth('account_created', 'Staff account created from an invitation for '.$user->email.', waiting to confirm the email.', $user);

        return redirect()->route('otp.verify');
    }
}
