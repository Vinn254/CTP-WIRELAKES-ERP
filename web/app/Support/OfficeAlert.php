<?php

namespace App\Support;

use App\Mail\ApplicationNotice;
use App\Mail\ApplicationReceived;
use App\Mail\NewMemberNotice;
use App\Mail\RegistrationNotice;
use App\Mail\RegistrationReceived;
use App\Models\Community;
use App\Models\HirerRegistration;
use App\Models\JobApplication;
use App\Models\ProgrammeApplication;
use App\Models\Trainer;
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
    /**
     * The addresses the programme is run from. The director's mailbox is always on it, because a notice that only
     * reaches a staff account is invisible the day that account is switched off, and the System Administrator is
     * on it too — every person who joins the register is their account to set up.
     */
    public static function recipients(): array
    {
        $to = array_values(array_filter([ProgrammeMail::director(), ProgrammeMail::systemAdmin()]));

        return array_values(array_unique(array_merge($to, ProgrammeMail::systemAdmins(),
            User::query()->where('role', 'superadmin')->where('active', true)
                ->whereNotNull('email')->pluck('email')->all())));
    }

    /** Announce a new CTP or E-Fundi application to the office and to the desk that runs that register. */
    public static function newMember(Model $member, string $what): void
    {
        $to = array_values(array_unique(array_merge(static::recipients(),
            ProgrammeMail::route($what === 'CTP' ? 'promoters' : 'efundi'))));

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
     * Announce a person or a place that just came onto the register and is not a member record — a trainer's
     * posting, a hirer, a community, a job application. The System Administrator hears it, as they hear about a
     * CTP or an E-Fundi, because theirs is the account that has to exist before the office can work on it.
     */
    public static function newRegistration(Model $record): void
    {
        $card = static::card($record);

        if (! $card) {
            return;
        }

        $to = array_values(array_unique(array_merge(static::recipients(),
            ProgrammeMail::route($card['desk']))));

        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new RegistrationNotice($card));
        } catch (Throwable $e) {
            logger()->warning('Office notice for the new '.$card['what'].' '.$card['name'].' was not sent: '.$e->getMessage());
        }
    }

    /**
     * What the office reads about one kind of record. Each type already names itself, so this only chooses which
     * of its own labels go on the notice, and which desk has to answer it.
     *
     * @return array{what:string,name:string,desk:string,rows:array<string,string>,link:string}|null
     */
    private static function card(Model $record): ?array
    {
        return match (true) {
            $record instanceof Trainer => [
                'what' => 'Trainer',
                'name' => $record->personName(),
                'desk' => 'training',
                // No line for what they teach: the office writes the programmes onto the posting just after the row
                // is created, so the notice names the person, the site and the money instead.
                'rows' => [
                    'Stand-in for' => $record->holderLabel(),
                    'Code' => $record->holderCode() ?: '—',
                    'Site' => $record->placeLabel() ?: '—',
                    'Hours a week' => $record->weekly_hours ?: '—',
                    'Rate' => $record->rate_ksh ? 'KSh '.number_format((float) $record->rate_ksh) : '—',
                    'Status' => $record->statusLabel(),
                ],
                'link' => route('admin.trainers.index'),
            ],
            $record instanceof HirerRegistration => [
                'what' => 'Hirer',
                'name' => (string) $record->organisation,
                'desk' => 'marketplace',
                'rows' => [
                    'Contact' => $record->contact_name ?: '—',
                    'Reference' => $record->reference ?: ($record->hirer_code ?: '—'),
                    'Phone' => $record->phone ?: '—',
                    'Email' => $record->email ?: '—',
                    'Where' => $record->locationLabel() ?: '—',
                    'Status' => $record->status,
                ],
                'link' => route('admin.hirers.index'),
            ],
            $record instanceof Community => [
                'what' => 'Community',
                'name' => (string) $record->name,
                'desk' => 'communities',
                'rows' => [
                    'Code' => $record->community_code ?: '—',
                    'Where' => $record->locationLabel() ?: '—',
                    'Population' => $record->population ? number_format($record->population) : '—',
                    'Households' => $record->households ? number_format($record->households) : '—',
                    'Status' => $record->statusLabel(),
                ],
                'link' => route('admin.communities.show', $record),
            ],
            $record instanceof JobApplication => [
                'what' => 'Job application',
                'name' => (string) $record->name,
                'desk' => 'hr',
                'rows' => [
                    'Applied for' => $record->opening?->title ?: '—',
                    'Phone' => $record->phone ?: '—',
                    'Email' => $record->email ?: '—',
                    'Town' => $record->town ?: '—',
                    'Status' => $record->statusLabel(),
                ],
                'link' => route('admin.careers.index'),
            ],
            default => null,
        };
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

        $areaUsers = ProgrammeMail::route(ProgrammeAreas::department($application->area));
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

    /**
     * Tell a CTP or E-Fundi applicant their application is in, and what to do about the registration fee before the
     * System Administrator can approve them. Same rule as above: a mail server that will not answer costs nobody
     * their application — the reference on the success screen already carries the same instructions.
     */
    public static function memberAcknowledge(Model $member, string $what): void
    {
        $email = Accounts::email($member->email ?? null);

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new RegistrationReceived($member, $what));
        } catch (Throwable $e) {
            logger()->warning('Acknowledgement for the '.$what.' application '.$member->reference.' was not sent: '.$e->getMessage());
        }
    }
}
