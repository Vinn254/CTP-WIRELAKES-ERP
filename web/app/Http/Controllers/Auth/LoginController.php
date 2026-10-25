<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        // A sign-in that is still waiting for its emailed code is not a sign-in.
        if ($request->session()->has('otp.pending')) {
            return redirect()->route('otp.verify');
        }

        return view('auth.login', ['idleMinutes' => (int) config('account.idle_minutes')]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = Accounts::email($credentials['email']);
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            Audit::auth('failed_sign_in', 'A sign-in for '.$email.' was turned away — the password did not match.');

            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        if (! in_array($user->role, ['admin', 'superadmin', 'ctp', 'fundi'], true)) {
            Audit::auth('failed_sign_in', $email.' is not a staff or member account.', $user);

            return back()->withErrors(['email' => 'This portal is for programme staff and members only.'])->onlyInput('email');
        }

        if (! $user->active) {
            // An account whose email was never confirmed is not a dead end: send the code again.
            if (! $user->email_verified_at && EmailOtp::start($user, $email, 'activate', $request, route('portal.dashboard'))) {
                return redirect()->route('otp.verify')
                    ->with('status', 'Your account is still waiting to confirm its email. A new code is on its way to '.$email.'.');
            }

            Audit::auth('failed_sign_in', $email.' tried to open a deactivated account.', $user);

            return back()->withErrors(['email' => 'This account has been deactivated. Contact a super admin.'])->onlyInput('email');
        }

        // Management is the part of the system that moves money and closes records, so every one of those sign-ins
        // is confirmed by a code to the holder's own email — a stolen password alone no longer opens the office.
        if (in_array($user->role, ['admin', 'superadmin'], true)) {
            if (! EmailOtp::start($user, $email, 'login', $request, route('admin.departments'))) {
                return back()->withErrors([
                    'email' => 'A code went to your email a moment ago. Wait a minute, then look in your inbox.',
                ])->onlyInput('email');
            }

            Audit::auth('otp_sent', 'Sign-in code sent to '.$email.' — the office login waits for it.', $user);

            return redirect()->route('otp.verify');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        Audit::auth('signed_in', 'Signed in to the member portal.', $user);

        return redirect()->intended(route('portal.dashboard'));
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            Audit::auth('signed_out', 'Signed out.', $user);
        }

        Auth::logout();
        $request->session()->forget('otp.pending');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Signed out.');
    }
}
