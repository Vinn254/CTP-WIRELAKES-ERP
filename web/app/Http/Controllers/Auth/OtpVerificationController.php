<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The emailed code screen. Two things land here: a new account confirming the address is really theirs, and an
 * office account proving it is them each time they sign in. Nothing is granted until the code matches — a pending
 * session is still a guest session.
 */
class OtpVerificationController extends Controller
{
    public function show(Request $request)
    {
        $pending = $request->session()->get('otp.pending');

        if (! $pending) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'email' => $pending['email'],
            'purpose' => $pending['purpose'],
            'minutes' => (int) config('account.otp.minutes'),
        ]);
    }

    public function verify(Request $request)
    {
        $pending = $request->session()->get('otp.pending');

        if (! $pending) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => 'required|string|max:10'], [], ['code' => 'confirmation code']);

        $otp = EmailOtp::open($pending['email'], $pending['purpose']);

        $user = User::find($pending['user_id']);

        if (! $user || ! $otp || ! $otp->verify($data['code'])) {
            return back()->withErrors([
                'code' => $otp
                    ? 'That code is not right. You have '.max(0, (int) config('account.otp.max_attempts') - $otp->attempts).' tries left before it stops working.'
                    : 'That code has expired. Ask for a new one.',
            ])->onlyInput();
        }

        $request->session()->forget('otp.pending');

        // A new account only wakes up once its address is proven; an office sign-in only completes here.
        if ($pending['purpose'] === 'activate') {
            $user->forceFill(['active' => true, 'email_verified_at' => now()])->save();
            Audit::auth('otp_verified', 'Confirmed the new '.$user->role.' account '.$user->email, $user);
        } else {
            Audit::auth('otp_verified', 'Confirmed the office sign-in for '.$user->email, $user);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $redirect = $pending['to'] ?? route(in_array($user->role, ['admin', 'superadmin'], true)
            ? 'admin.departments'
            : 'portal.dashboard');

        return filled($pending['flash'] ?? null)
            ? redirect()->to($redirect)->with('status', $pending['flash'])
            : redirect()->to($redirect);
    }

    public function resend(Request $request)
    {
        $pending = $request->session()->get('otp.pending');

        if (! $pending) {
            return redirect()->route('login');
        }

        $user = User::find($pending['user_id']);
        $sent = EmailOtp::issue($user, $pending['email'], $pending['purpose']);

        return $sent
            ? back()->with('status', 'A new code is on its way to '.$pending['email'].'.')
            : back()->with('status', 'Hold on a minute — the last code is still on its way.');
    }

    public function cancel(Request $request)
    {
        $request->session()->forget('otp.pending');

        return redirect()->route('login')->with('status', 'Sign-in stopped. Nothing was changed on the account.');
    }
}
