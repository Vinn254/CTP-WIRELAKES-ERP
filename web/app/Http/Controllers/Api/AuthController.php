<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetEmail;
use App\Models\CtpRegistration;
use App\Models\EmailOtp;
use App\Models\FundiRegistration;
use App\Models\PasswordResetLink;
use App\Models\User;
use App\Support\Accounts;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The phone app's side of the same rules the website keeps: one person one account, an emailed code that proves the
 * address, a reset link that dies by itself, and a deactivated account that cannot get a token.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $email = Accounts::email($data['email']);

        try {
            Accounts::guard(['email' => $email], null, [User::class, CtpRegistration::class, FundiRegistration::class]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // The account sleeps until the emailed code is typed back.
        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => $data['password'],
        ]);

        if (! EmailOtp::enabled()) {
            $user->forceFill(['active' => true, 'email_verified_at' => now()])->save();
            Audit::auth('account_created', 'App account created for '.$email.' — no code asked for in this environment.', $user);

            return response()->json(['user' => $user, 'token' => $user->createToken('mobile')->plainTextToken], 201);
        }

        $challenge = $this->sendOtp($user, $email, 'activate');

        if (! $challenge) {
            return response()->json([
                'message' => 'A code went to that address a moment ago. Wait a minute, then confirm it.',
            ], 429);
        }

        Audit::auth('account_created', 'App account created for '.$email.', waiting to confirm the email.', $user);

        return response()->json([
            'otp_required' => true,
            'challenge' => $challenge,
            'minutes' => (int) config('account.otp.minutes'),
            'message' => 'We emailed a code to '.$email.'. Confirm it to finish setting up the account.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Accounts::email($data['email']);
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Audit::auth('failed_sign_in', 'An app sign-in for '.$email.' was turned away — the password did not match.');

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->active) {
            // An account whose email was never confirmed is not shut out for good: send the code again.
            if (! $user->email_verified_at) {
                if (! EmailOtp::enabled()) {
                    $user->forceFill(['active' => true, 'email_verified_at' => now()])->save();

                    return $this->tokenFor($user->fresh());
                }

                if ($challenge = $this->sendOtp($user, $email, 'activate')) {
                    return response()->json([
                        'otp_required' => true,
                        'challenge' => $challenge,
                        'minutes' => (int) config('account.otp.minutes'),
                        'message' => 'Confirm your email address with the code we just sent.',
                    ]);
                }
            }

            Audit::auth('failed_sign_in', $email.' tried to open a deactivated account from the app.', $user);

            return response()->json(['message' => 'This account is switched off. Contact a super admin.'], 403);
        }

        if (in_array($user->role, ['admin', 'superadmin'], true) && EmailOtp::enabled()) {
            $challenge = $this->sendOtp($user, $email, 'login');

            if (! $challenge) {
                return response()->json(['message' => 'A code went to your email a moment ago. Wait a minute, then check it.'], 429);
            }

            Audit::auth('otp_sent', 'App sign-in code sent to '.$email.' — the office login waits for it.', $user);

            return response()->json([
                'otp_required' => true,
                'challenge' => $challenge,
                'minutes' => (int) config('account.otp.minutes'),
                'message' => 'Enter the code we emailed you to open the office.',
            ]);
        }

        return $this->tokenFor($user);
    }

    /** The token a confirmed account keeps. The app never sees a code it did not have to send. */
    private function tokenFor(User $user): JsonResponse
    {
        return response()->json(['user' => $user, 'token' => $user->createToken('mobile')->plainTextToken]);
    }

    /** Turn a challenge and a code into the token the app keeps. */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:48'],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $otp = EmailOtp::forChallenge($data['challenge']);
        $user = $otp?->user_id ? User::find($otp->user_id) : null;

        if (! $user || ! $otp || ! $otp->verify($data['code'])) {
            return response()->json([
                'message' => $otp && $otp->isLive()
                    ? 'That code is not right. You have '.max(0, (int) config('account.otp.max_attempts') - $otp->attempts).' tries left.'
                    : 'That code has expired. Ask for a new one.',
            ], 422);
        }

        if ($otp->purpose === 'activate') {
            $user->forceFill(['active' => true, 'email_verified_at' => now()])->save();
        }

        Audit::auth('otp_verified', 'Confirmed an app '.($otp->purpose === 'login' ? 'sign-in' : 'account').' for '.$user->email.'.', $user);

        return response()->json([
            'user' => $user->fresh(),
            'token' => $user->createToken('mobile')->plainTextToken,
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge' => ['required', 'string', 'size:48']]);

        $otp = EmailOtp::forChallenge($data['challenge']);

        if (! $otp) {
            return response()->json(['message' => 'Ask for a code by signing in again.'], 422);
        }

        $challenge = $this->sendOtp($otp->user_id ? User::find($otp->user_id) : null, $otp->email, $otp->purpose);

        return $challenge
            ? response()->json(['challenge' => $challenge, 'message' => 'A new code is on its way.'])
            : response()->json(['message' => 'Hold on a minute before asking for another code.'], 429);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Accounts::email($data['email']);
        $user = User::where('email', $email)->first();
        $issued = $user ? PasswordResetLink::issue($email) : null;

        if ($user && $issued) {
            Mail::to($email)->send(new PasswordResetEmail(
                route('password.reset', ['token' => $issued['token']]),
                (int) config('account.reset.minutes')
            ));

            Audit::auth('reset_sent', 'App reset link #'.$issued['link']->sequence.' sent to '.$email.'.', $user);
        } else {
            Audit::auth('reset_refused', $email.' asked for a reset link the programme would not send.');
        }

        // The same answer either way: this form must not tell a stranger which emails are on the programme.
        return response()->json([
            'message' => 'If that email is on the programme, a reset link is on its way. It works for '
                .config('account.reset.minutes').' minutes and can be used once.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $link = PasswordResetLink::findValid($data['token']);
        $user = $link ? User::where('email', $link->email)->first() : null;

        if (! $user) {
            return response()->json(['message' => 'That reset link has expired or was already used. Ask for a new one.'], 422);
        }

        $user->forceFill(['password' => $data['password']])->save();
        $link->markUsed();
        // Every token the person held is spent: a reset means the old sessions are no longer trusted.
        $user->tokens()->delete();

        Audit::auth('reset_used', 'Password changed through an app reset link.', $user);

        return response()->json(['message' => 'Your password is changed. Sign in again with it.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = \App\Support\MemberPortal::resolve($user);

        return response()->json([
            'user' => $user,
            'member_type' => $member ? ($member instanceof \App\Models\FundiRegistration ? 'fundi' : 'ctp') : null,
            'member_name' => $member?->full_name,
            'staff' => in_array($user->role, ['admin', 'superadmin'], true),
            'idle_minutes' => (int) config('account.idle_minutes'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /** The phone gets back a challenge string, never the code itself. */
    private function sendOtp(?User $user, string $email, string $purpose): ?string
    {
        $sent = EmailOtp::issue($user, $email, $purpose);

        return $sent['challenge'] ?? null;
    }
}
