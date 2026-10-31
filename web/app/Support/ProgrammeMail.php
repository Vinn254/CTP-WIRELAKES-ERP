<?php

namespace App\Support;

use App\Mail\ContactNotice;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The programme's own mailboxes. A public message, an application or an office notice has to land somewhere a
 * person reads, so each desk can be given one address here and every writer goes through this class.
 *
 * The address on this screen always wins; a desk with nothing typed keeps its old route, which is the office
 * accounts holding that module's tick (Departments::staffEmails). That way switching a mailbox on adds a reader
 * rather than cutting the staff out of their own desk.
 */
class ProgrammeMail
{
    /** The domain every programme address sits on. */
    public const DOMAIN = 'wirelakestechnologies.com';

    /**
     * Where each module's post is delivered when the office has not typed anything. Only the desks whose mailbox
     * is unambiguous are filled; the rest are left for the settings screen, because a guessed address quietly
     * loses a message.
     *
     * @var array<string, string>
     */
    private const DESKS = [
        'membership' => 'info',
        'training' => 'registra',
        'finance' => 'finance',
        'procurement' => 'procurement',
        'hr' => 'hr',
        'technology' => 'sysadmin',
    ];

    /** The settings key a desk's mailbox is stored under. */
    public static function key(string $slug): string
    {
        return 'desk_email_'.$slug;
    }

    /** The address the office typed for a desk, or the programme default it ships with. Null when neither exists. */
    public static function desk(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        $typed = trim((string) Setting::get(static::key($slug), ''));

        if ($typed !== '') {
            return mb_strtolower($typed);
        }

        return isset(static::DESKS[$slug]) ? static::DESKS[$slug].'@'.static::DOMAIN : null;
    }

    /** Where the whole programme is answered — the director's mailbox and the copy that goes to it. */
    public static function director(): ?string
    {
        return static::desk('director') ?: 'director@'.static::DOMAIN;
    }

    /**
     * The mailbox that hears about every person who joins. The System Administrator is the one account that must
     * never learn about a registration second-hand, so it is named here rather than left to a department tick.
     */
    public static function systemAdmin(): ?string
    {
        return static::desk('sysadmin') ?: 'sysadmin@'.static::DOMAIN;
    }

    /**
     * The accounts that hold the System Administrator title, so a notice reaches the person actually sitting on
     * that desk and not only the mailbox. Read from the staff list, which is where the office sets the title.
     *
     * @return array<int, string>
     */
    public static function systemAdmins(): array
    {
        return User::query()->where('active', true)->whereNotNull('email')->where('role', 'admin')
            ->get(['email', 'roles'])
            ->filter(fn (User $user) => in_array('system_administrator', (array) ($user->roles ?? []), true))
            ->pluck('email')->map(fn ($address) => mb_strtolower(trim($address)))->all();
    }

    /**
     * Every address that must see a message about one module: its own desk first, then the office staff who hold
     * the tick, then the general mailbox and the director, so nothing is stranded on a desk nobody watches.
     *
     * @return array<int, string>
     */
    public static function route(?string $slug): array
    {
        $to = [static::desk($slug), ...Departments::staffEmails($slug), static::desk('membership'), static::director()];

        return array_values(array_unique(array_filter(array_map(
            fn ($address) => is_string($address) ? mb_strtolower(trim($address)) : $address, $to))));
    }

    /**
     * The mailboxes the office can point at, with what each one answers to today. Everything here is read by
     * route(), so a desk that is never typed stays on the staff accounts that hold its tick.
     *
     * @return array<int, array{key:string,label:string,value:?string,read:string}>
     */
    public static function rows(): array
    {
        $rows = [[
            'key' => static::key('director'),
            'label' => 'Director — everything that happens',
            'value' => static::director(),
            'read' => 'Copied on a new CTP or E-Fundi application, a request from a public page and a message from the website.',
        ], [
            'key' => static::key('sysadmin'),
            'label' => 'System Administrator — every registration',
            'value' => static::systemAdmin(),
            'read' => 'Copied on every person who joins: a CTP, an E-Fundi, a trainer, a hirer, a community or a job applicant.',
        ]];

        foreach (Departments::live() as $d) {
            $rows[] = [
                'key' => static::key($d['slug']),
                'label' => $d['name'],
                'value' => static::desk($d['slug']),
                'read' => 'Where a message about this module is delivered when someone sends it from the website.',
            ];
        }

        return $rows;
    }

    /**
     * What a visitor on the public site calls the desk they want. One line per module that actually answers the
     * public, in the order the office appears on screen.
     *
     * @return array<string, string>  slug => label
     */
    public static function topics(): array
    {
        $topics = [];

        foreach (Departments::live() as $d) {
            if (in_array($d['slug'], ['membership', 'promoters', 'communities', 'training', 'efundi',
                'marketplace', 'execution', 'construction', 'technology', 'ewaste', 'finance',
                'procurement', 'hr'], true)) {
                $topics[$d['slug']] = $d['name'];
            }
        }

        return $topics;
    }

    /**
     * Carry a message from the website to the desk it was sent for. A mail server that will not answer never costs
     * the message — it is on the Public messages screen either way, and the reason it did not post is logged.
     */
    public static function notifyOffice(ContactMessage $note): void
    {
        $to = static::route($note->topic);

        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new ContactNotice($note, route('admin.messages.index')));
        } catch (Throwable $e) {
            logger()->warning('The website message #'.$note->id.' about '.$note->topic.' was not mailed: '.$e->getMessage());
        }
    }
}
