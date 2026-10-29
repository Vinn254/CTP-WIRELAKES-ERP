<?php

namespace App\Support;

use App\Models\FundiRegistration;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The workspace a staff account actually works from.
 *
 * A super admin runs the programme, so they navigate by module. Everyone else holds job titles, and a title is
 * worked as a list of screens — not as a stack of numbered modules. This turns the departments a person's roles
 * open into those screens, grouped by the title that grants them, each screen listed once however many titles
 * share it. Someone holding two titles is offered both as cards and works one of them at a time.
 */
class Workspace
{
    /** @return array<int, array{label: string, note: string, items: array<int, array<string, mixed>>}> */
    public static function groups(?User $user, ?string $role = null): array
    {
        // Someone who holds more than one title works as one of them at a time, whichever desk they chose on landing.
        $role ??= static::acting($user);

        $visible = Departments::visibleSlugs($user);
        $held = $user ? $user->roleSlugs() : [];
        $seen = [];
        $groups = [];

        // Their own teaching first: a trainer who also runs a department works both from one sidebar.
        if (static::trainerDesk($user)) {
            $groups[] = [
                'slug' => 'trainer',
                'label' => Roles::all()['trainer']['name'],
                'note' => Roles::all()['trainer']['desc'],
                'items' => array_merge(
                    [
                        ['label' => 'My teaching', 'icon' => 'award', 'route' => ['admin.teaching.index'], 'same' => true,
                            'note' => 'The cohorts you take and the marks you record for them', 'department' => 'training'],
                    ],
                    static::paperLinks($user),
                ),
            ];
        }

        foreach ($held as $slug) {
            $profile = Roles::all()[$slug] ?? null;

            if (! $profile) {
                continue;
            }

            $items = static::take($user, array_intersect($profile['departments'], $visible), $seen);

            if ($items) {
                $groups[] = ['slug' => $slug, 'label' => $profile['name'], 'note' => $profile['desc'], 'items' => $items];
            }
        }

        // Departments opened by hand rather than by a title, and the whole list for an account that holds none yet.
        foreach (array_diff($visible, Roles::departmentsFor($held)) as $department) {
            $items = static::take($user, [$department], $seen);

            if ($items) {
                $groups[] = [
                    'slug' => null,
                    'label' => Departments::find($department)['name'] ?? $department,
                    'note' => Departments::find($department)['desc'] ?? '',
                    'items' => $items,
                ];
            }
        }

        if ($role !== null) {
            $groups = array_values(array_filter($groups, fn (array $g) => ($g['slug'] ?? null) === $role));
        }

        // The own desk goes with the person, not the title: a week sheet and a leave book, wherever they sit.
        $listed = collect($groups)->flatMap(fn (array $g) => collect($g['items'])
            ->map(fn (array $i) => $i['route'][0] ?? ''));

        if (! $listed->contains('admin.mytime.index')) {
            $groups[] = [
                'label' => 'My own desk',
                'note' => 'The week you fill and sign, and the leave you book with Human Resources',
                'items' => [
                    ['label' => 'My time log', 'icon' => 'clock', 'route' => ['admin.mytime.index'],
                        'note' => 'Pick the month and the week of it, write Monday to Friday, sign the week'],
                    ['label' => 'My leave', 'icon' => 'mail', 'route' => ['admin.myleave.index'],
                        'note' => 'Your balances and an application to Human Resources'],
                ],
            ];
        }

        return $groups;
    }

    /** The one job title this person is working as, when they hold several and have chosen one of them. */
    public static function acting(?User $user): ?string
    {
        $held = $user ? $user->roleSlugs() : [];
        $picked = session('workspace_role');

        return count($held) > 1 && is_string($picked) && in_array($picked, $held, true) ? $picked : null;
    }

    /** The desks to choose from when an account holds more than one job title: a card per title it actually opens. */
    public static function cards(?User $user): array
    {
        $cards = [];

        foreach ($user ? $user->roleSlugs() : [] as $slug) {
            // The card counts exactly the screens that title's workspace will show, its own desk included.
            $items = collect(static::groups($user, $slug))->flatMap(fn (array $g) => $g['items']);

            if ($items->isEmpty()) {
                continue;
            }

            $cards[] = [
                'slug' => $slug,
                'label' => Roles::all()[$slug]['name'],
                'note' => Roles::all()[$slug]['desc'],
                'icon' => $items->first()['icon'] ?? 'grid',
                'pages' => $items->count(),
                'waiting' => $items->sum(fn (array $i) => Departments::badge($i['badge'] ?? null)),
            ];
        }

        return $cards;
    }

    /**
     * Does this account work a trainer desk at all?
     *
     * The office licenses a trainer site by site, but it also hands out the Trainer job title first. Someone holding
     * the title gets the desk — and the empty state that tells them which site is still missing — rather than a
     * refusal that reads as a fault. A cohort stays out of reach until a posting under their name is approved.
     */
    public static function trainerDesk(?User $user): bool
    {
        return $user !== null
            && (Trainer::forAccount($user)->isNotEmpty() || in_array('trainer', $user->roleSlugs(), true));
    }

    /**
     * The five papers of the one class a trainer holds, straight on the sidebar.
     *
     * With two or more cohorts the papers are opened from My teaching, where the class is picked first; listing them
     * here would point at a class the trainer did not choose.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paperLinks(?User $user): array
    {
        $taught = TeachingDesk::postings($user)->flatMap(fn (Trainer $t) => $t->cohorts)
            ->where('status', '!=', 'draft')->unique('id')->values();

        if ($taught->count() !== 1) {
            return [];
        }

        $cohort = $taught->first()->id;

        $papers = [
            ['Attendance', 'clipboard', 'register', 'Tick the class roll for a day you taught, and hand the day in'],
            ['Marks', 'check', 'marks', 'The CATs, assignments, theory and practical marks per unit'],
            ['Timetable', 'clock', 'timetable', 'The week of classes you propose for the Registrar to approve'],
            ['Lesson plan', 'cap', 'lessons', 'What you will teach, lesson 1 to the last lesson of the period'],
            ['Material', 'image', 'materials', 'The notes, slides and assignments you hand each class'],
        ];

        return array_map(fn (array $p) => [
            'label' => $p[0], 'icon' => $p[1], 'route' => ['admin.teaching.'.$p[2], ['cohort' => $cohort]],
            'note' => $p[3], 'department' => 'training', 'same' => true,
        ], $papers);
    }

    /**
     * The E-Fundi record of the person behind this staff account, when they sit an approved one as a trainer.
     * Matched on the account's own email — the programme keeps one identity per person — never on a url or a
     * picked id, so a desk can only ever show the training record of the person signed in.
     */
    public static function teaching(?User $user): ?FundiRegistration
    {
        if (! $user || ! $user->email || ! in_array('trainer', $user->roleSlugs(), true)) {
            return null;
        }

        $fundi = FundiRegistration::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($user->email)])
            ->first();

        return $fundi && Trainer::approved()->where('fundi_id', $fundi->id)->exists() ? $fundi : null;
    }

    /** Every screen this account may work, in one flat list. */
    public static function screens(?User $user): array
    {
        return array_merge(...array_map(fn (array $g) => $g['items'], static::groups($user)));
    }

    /** The screens waiting on someone, with how much is waiting. */
    public static function waiting(?User $user): array
    {
        return array_values(array_filter(array_map(function (array $item) {
            $count = Departments::badge($item['badge'] ?? null);

            return $count ? $item + ['waiting' => $count] : null;
        }, static::screens($user))));
    }

    /**
     * @param  array<int, string>  $departments
     * @param  array<string, bool>  $seen  screens already placed under an earlier title, passed by reference
     */
    private static function take(?User $user, array $departments, array &$seen): array
    {
        $items = [];

        foreach ($departments as $slug) {
            foreach (Departments::nav($slug) as $item) {
                // Two departments are given a copy of the same register (the trail, say); a person sees it once.
                $key = 'label:'.$item['label'];

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $items[] = $item + ['department' => $slug, 'note' => static::plain(Departments::note($item['route'][0]))];
            }
        }

        return $items;
    }

    /** A description read on a screen, not in the blueprint: the clause references stay in the module notes. */
    public static function plain(string $note): string
    {
        return trim((string) preg_replace('/\s*\([^)]*§[^)]*\)/u', '', $note));
    }
}
