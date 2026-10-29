<?php

namespace App\Support;

use App\Models\Setting;
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
            ($what ?: 'Paying this out').' is a programme executive\'s action. '
            .'Staff prepare the request and the paperwork; the money is only released by an executive account.');
    }

    /** @param  class-string<User>  $nothing — kept for readability where a caller wants the role name */
    public static function actorLabel(): string
    {
        return static::isExecutive() ? 'Programme executive' : 'Programme staff';
    }

    /**
     * The one payment address the programme gives anyone it asks for money — on an invoice, in a member portal, at a
     * counter. It is a programme setting so the office can change it without a code edit.
     */
    public static function paybill(): array
    {
        return [
            'number' => Setting::get('paybill_number', '146320'),
            'account' => Setting::get('paybill_account', '1039062'),
        ];
    }

    public static function paybillLine(): string
    {
        $p = static::paybill();

        return 'For any type of payment use this Paybill '.$p['number'].', account number '.$p['account'].'.';
    }

    /**
     * Whether the M-Pesa line is live with Safaricom. Switched off, every payment screen asks the office to write the
     * payment in by hand; switched on, it still can — a counter sale or a cheque never waits on an integration.
     */
    public static function mpesaLive(): bool
    {
        return Setting::get('mpesa_payment_line', 'off') === 'on';
    }
}
