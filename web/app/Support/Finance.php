<?php

namespace App\Support;

use App\Models\User;

/** Money rules shared by every Module 11 screen, so no screen invents its own formatting or its own authority. */
class Finance
{
    /** KSh 1,500 — whole shilling, or two decimals when kobo actually moves. */
    public static function ksh(?float $amount): string
    {
        $amount = (float) ($amount ?? 0);
        $decimals = abs($amount - round($amount)) > 0.004 ? 2 : 0;

        return 'KSh '.number_format($amount, $decimals);
    }

    public static function isExecutive(): bool
    {
        return auth()->user()?->role === 'superadmin';
    }

    /**
     * Money leaving the programme is signed off by a programme executive — the same rule as a certificate.
     * Staff prepare the request, quote it, receive the goods and match the paperwork; the payment itself is issued here.
     */
    public static function requireRelease(?string $what = null): void
    {
        abort_unless(static::isExecutive(), 403,
            ($what ?: 'Paying this out').' is a programme executive\'s action (§14.4 step 8, §14.6 steps 5–7). '
            .'Staff prepare the request and the paperwork; the money is only released by an executive account.');
    }

    /** @param  class-string<User>  $nothing — kept for readability where a caller wants the role name */
    public static function actorLabel(): string
    {
        return static::isExecutive() ? 'Programme executive' : 'Programme staff';
    }
}
