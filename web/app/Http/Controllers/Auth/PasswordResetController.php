<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetEmail;
use App\Models\PasswordResetLink;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Forgot the password. The link is minted here, mailed here and spent here: it lives for the minutes set in
 * config/account.php, opens exactly one door, and one address may only ask for its daily allowance — so nobody can
 * use the programme as a mail cannon, and an old link found in a recycled inbox is worth nothing.
 */
class PasswordResetController extends Controller
{
    public function show()
    {
        return view('auth.forgot-password', ['minutes' => (int) config('account.reset.minutes')]);
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => 'required|email'], [], ['email' => 'email address']);
        $email = Accounts::email($data['email']);
        $user = User::where('email', $email)->first();

        // The answer is the same whether or not the address is on the programme: a stranger cannot use this form to
        // find out which staff emails exist.
        $issued = $user ? PasswordResetLink::issue($email) : null;

        if ($user && $issued) {
            Mail::to($email)->send(new PasswordResetEmail(
                route('password.reset', ['token' => $issued['token']]),
                (int) config('account.reset.minutes')
            ));

            Audit::auth('reset_sent', 'Reset link #'.$issued['link']->sequence.' of '
                .config('account.reset.per_day').' a day sent to '.$email.'.', $user);
        } elseif ($user) {
            Audit::auth('reset_refused', $email.' asked for more reset links than the daily allowance.', $user);
        } else {
            Audit::auth('reset_refused', 'A reset link was asked for an address that is not on the programme: '.$email.'.');
        }

        return back()->with('status', 'If that email is on the programme, a reset link is on its way. It works for '
            .config('account.reset.minutes').' minutes and can be used once only. Check your spam folder, and try again '
            .'tomorrow if you have already asked ' .config('account.reset.per_day').' times today.');
    }

    public function showReset(string $token)
    {
        $link = PasswordResetLink::findValid($token);

        if (! $link) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'That reset link has expired or was already used. Ask for a new one.']);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $link->email,
            'minutes' => $link->minutesLeft(),
        ]);
    }

    public function update(Request $request, string $token)
    {
        $link = PasswordResetLink::findValid($token);

        if (! $link) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'That reset link has expired or was already used. Ask for a new one.']);
        }

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [], ['password' => 'new password']);

        $user = User::where('email', $link->email)->first();

        if (! $user) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'That address no longer has an account on the programme.']);
        }

        // The password column is never mass-assigned from a request, so the change is written directly.
        $user->forceFill(['password' => $data['password']])->save();
        $link->markUsed();

        Audit::auth('reset_used', 'Password changed through a reset link. The next sign-in still needs the emailed code.', $user);

        return redirect()->route('login')->with('status', 'Your password is changed. Sign in with it now.');
    }
}
