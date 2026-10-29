<?php

namespace App\Support;

use App\Mail\ApplicationNotice;
use App\Mail\ApplicationReceived;
use App\Mail\NewMemberNotice;
use App\Models\ProgrammeApplication;
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

    /**
     * Announce an application that came in from a public area page. The executive hears it, and so does every desk
     * whose area it is — the training office answers a trainee, the E-Waste desk answers a collector.
     */
    public static function newApplication(ProgrammeApplication $application): void
    {
        // Each recipient is sent the screen their own account can open: the executive reads the register,
        // the area desk gets its own copy of the line.
        static::mailApplication($application, static::recipients(), route('admin.applications.index'));

        $areaUsers = Departments::staffEmails(ProgrammeAreas::department($application->area));
        static::mailApplication($application, $areaUsers, route('admin.host.applications.show', [
            'application' => $application->id,
            'department' => ProgrammeAreas::department($application->area),
        ]));
    }

    protected static function mailApplication(ProgrammeApplication $application, array $to, string $link): void
    {
        $to = array_values(array_unique($to));

        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new ApplicationNotice($application, $link));
        } catch (Throwable $e) {
            logger()->warning('Office notice for the application '.$application->reference.' was not sent: '.$e->getMessage());
        }
    }

    /**
     * Tell the person their application landed, and how long the office has to answer. A mail server that will not
     * answer never throws the application away — it is on the register either way.
     */
    public static function applicantAcknowledge(ProgrammeApplication $application): void
    {
        try {
            Mail::to($application->email)->send(new ApplicationReceived($application));
        } catch (Throwable $e) {
            logger()->warning('Acknowledgement for the application '.$application->reference.' was not sent: '.$e->getMessage());
        }
    }
}
