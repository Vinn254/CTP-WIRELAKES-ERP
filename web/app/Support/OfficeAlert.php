<?php

namespace App\Support;

use App\Mail\NewMemberNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One place for telling the office something happened. A notice must never cost a person their application, so
 * a mail server that will not answer is written to the log and the registration stands.
 */
class OfficeAlert
{
    /** The addresses the programme is run from. */
    public static function recipients(): array
    {
        return User::query()->where('role', 'superadmin')->where('active', true)
            ->whereNotNull('email')->pluck('email')->all();
    }

    /** Announce a new CTP or E-Fundi application to the office. */
    public static function newMember(Model $member, string $what): void
    {
        $to = static::recipients();

        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new NewMemberNotice($member, $what));
        } catch (Throwable $e) {
            logger()->warning('Office notice for the new '.$what.' '.$member->reference.' was not sent: '.$e->getMessage());
        }
    }
}
