<?php

namespace App\Models;

use App\Support\Accounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The forgot-password link. It carries a random token that is only ever stored hashed, it dies by itself after the
 * configured window, it works once, and one address may only be sent the configured number per day — so a stolen
 * mailbox cannot keep minting doors and a spammer cannot use the programme as a mail cannon.
 */
class PasswordResetLink extends Model
{
    protected $fillable = ['email', 'token_hash', 'sequence', 'expires_at', 'used_at', 'sent_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    /**
     * @return array{link: self, token: string}|null null once the address has used today's links
     */
    public static function issue(string $email): ?array
    {
        $email = Accounts::email($email);
        $limit = (int) config('account.reset.per_day');

        $today = static::where('email', $email)
            ->where('created_at', '>=', now()->startOfDay())->count();

        if ($today >= $limit) {
            return null;
        }

        // Every older link for the address is shut: only the newest one can open the door.
        static::where('email', $email)->whereNull('used_at')->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $token = Str::random(48);

        $link = static::create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'sequence' => $today + 1,
            'expires_at' => now()->addMinutes((int) config('account.reset.minutes')),
            'sent_at' => now(),
        ]);

        return ['link' => $link, 'token' => $token];
    }

    public static function findValid(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now(), 'expires_at' => now()]);
    }

    public function minutesLeft(): int
    {
        return max(0, (int) now()->diffInMinutes($this->expires_at, false));
    }
}
