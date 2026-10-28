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
 * share it.
 */
class Workspace
{
    /** @return array<int, array{label: string, note: string, items: array<int, array<string, mixed>>}> */
    public static function groups(?User $user): array
    {
        $visible = Departments::visibleSlugs($user);
        $held = $user ? $user->roleSlugs() : [];
        $seen = [];
        $groups = [];

        // Their own teaching first: a trainer who also runs a department works both from one sidebar.
        if (Trainer::forAccount($user)->isNotEmpty()) {
            $groups[] = [
                'label' => Roles::all()['trainer']['name'],
                'note' => Roles::all()['trainer']['desc'],
                'items' => [
                    ['label' => 'My teaching', 'icon' => 'award', 'route' => ['admin.teaching.index'],
                        'note' => 'The cohorts you take and the marks you record for them', 'department' => 'training'],
                ],
            ];
        }

        foreach ($held as $slug) {
            $profile = Roles::all()[$slug] ?? null;

            if (! $profile) {
                continue;
            }

            $items = static::take($user, array_intersect($profile['departments'], $visible), $seen);

            if ($items) {
                $groups[] = ['label' => $profile['name'], 'note' => $profile['desc'], 'items' => $items];
            }
        }

        // Departments opened by hand rather than by a title, and the whole list for an account that holds none yet.
        foreach (array_diff($visible, Roles::departmentsFor($held)) as $department) {
            $items = static::take($user, [$department], $seen);

            if ($items) {
                $groups[] = [
                    'label' => Departments::find($department)['name'] ?? $department,
                    'note' => Departments::find($department)['desc'] ?? '',
                    'items' => $items,
                ];
            }
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
