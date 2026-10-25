<?php

namespace App\Models;

use App\Mail\OtpEmail;
use App\Support\Accounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The emailed code that proves the address is the holder's own. Only the hash of the code and of the challenge are
 * kept: a leaked table must not let anybody pass a verification screen.
 */
class EmailOtp extends Model
{
    protected $fillable = [
        'user_id', 'email', 'purpose', 'code_hash', 'challenge_hash', 'attempts', 'expires_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    /** A fresh code cannot be asked for more than this often, per address. */
    private const RESEND_SECONDS = 60;

    public function isLive(): bool
    {
        return ! $this->consumed_at
            && $this->expires_at->isFuture()
            && $this->attempts < (int) config('account.otp.max_attempts');
    }

    /**
     * Send a new code to the address, retiring whatever was open before it.
     *
     * @return array{otp: self, code: string, challenge: string}|null null while the last code is still cooling off
     */
    public static function issue(?User $user, string $email, string $purpose): ?array
    {
        $email = Accounts::email($email);

        $latest = static::where('email', $email)->where('purpose', $purpose)->latest('id')->first();

        if ($latest && $latest->created_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            return null;
        }

        // Only the newest code can be used, so an old one in an old email is worth nothing.
        static::where('email', $email)->where('purpose', $purpose)
            ->whereNull('consumed_at')->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $code = str_pad((string) random_int(0, 10 ** (int) config('account.otp.digits') - 1), (int) config('account.otp.digits'), '0', STR_PAD_LEFT);
        $challenge = Str::random(48);

        $otp = static::create([
            'user_id' => $user?->id,
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'challenge_hash' => hash('sha256', $challenge),
            'expires_at' => now()->addMinutes((int) config('account.otp.minutes')),
        ]);

        // Sent now, not queued: shared hosting runs no queue worker, and a code that arrives after the person has
        // given up is as good as lost.
        Mail::to($email)->send(new OtpEmail($code, $purpose));

        return ['otp' => $otp, 'code' => $code, 'challenge' => $challenge];
    }

    /**
     * Send a code and park the request as pending: the screens that lead here only ever hand over an address,
     * a purpose and where to go once the code matches.
     */
    public static function start(?User $user, string $email, string $purpose, Request $request, ?string $to = null, ?string $flash = null): bool
    {
        if (! static::issue($user, $email, $purpose)) {
            return false;
        }

        $request->session()->put('otp.pending', [
            'user_id' => $user?->id,
            'email' => Accounts::email($email),
            'purpose' => $purpose,
            'to' => $to,
            'flash' => $flash,
        ]);

        return true;
    }

    /** The open code for this address and purpose, if one is still usable. */
    public static function open(string $email, string $purpose): ?self
    {
        $otp = static::where('email', Accounts::email($email))
            ->where('purpose', $purpose)->latest('id')->first();

        return $otp?->isLive() ? $otp : null;
    }

    /** The phone app names a code by its challenge, never by an email. */
    public static function forChallenge(string $challenge): ?self
    {
        return static::where('challenge_hash', hash('sha256', $challenge))->first();
    }

    public function verify(string $code): bool
    {
        if (! $this->isLive() || ! Hash::check($code, $this->code_hash)) {
            $this->increment('attempts');

            return false;
        }

        $this->update(['consumed_at' => now()]);

        return true;
    }
}
