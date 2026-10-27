<?php

namespace App\Support;

use App\Models\User;

/**
 * The office job titles, and the departments each one opens.
 *
 * A role is only ever a bundle of department ticks — the ticks themselves stay the permission, which is what
 * EnsureUserIsStaff and the sidebar already read. That keeps the audited gate as the single source of truth while
 * letting one person hold two jobs (a Registrar who also sits on the Projects Desk) without a new permission layer.
 */
class Roles
{
    public const PROFILES = [
        'system_administrator' => [
            'name' => 'System Administrator',
            'desc' => 'Accounts, site content, every project desk and the impact reports',
            'departments' => ['membership', 'marketplace', 'execution', 'construction', 'technology', 'ewaste', 'me_gis'],
        ],
        'academic_registrar' => [
            'name' => 'Academic Registrar',
            'desc' => 'The learner record end to end: CTPs, communities, training and E-Fundi grades',
            'departments' => ['promoters', 'communities', 'training', 'efundi'],
        ],
        'finance_hr' => [
            'name' => 'Finance & HR',
            'desc' => 'Budgets, the money register, purchases and stock, with the delivery register in view',
            'departments' => ['finance', 'execution'],
        ],
        'projects_desk' => [
            'name' => 'Projects Desk',
            'desc' => 'Matching, delivery, construction, technology and e-waste',
            'departments' => ['marketplace', 'execution', 'construction', 'technology', 'ewaste'],
        ],
    ];

    /** @return array<string, array{name: string, desc: string, departments: array<int, string>}> */
    public static function all(): array
    {
        return self::PROFILES;
    }

    /**
     * The departments a set of roles opens. An unknown slug contributes nothing rather than throwing, so a role
     * retired later never strands an account.
     *
     * @param  array<int, string>  $roleSlugs
     */
    public static function departmentsFor(array $roleSlugs): array
    {
        $slugs = [];
        foreach ($roleSlugs as $slug) {
            $slugs = array_merge($slugs, self::PROFILES[$slug]['departments'] ?? []);
        }

        return array_values(array_unique($slugs));
    }

    /** @param  array<int, string>  $roleSlugs */
    public static function namesFor(array $roleSlugs): array
    {
        return array_values(array_filter(array_map(
            fn ($slug) => self::PROFILES[$slug]['name'] ?? null,
            $roleSlugs
        )));
    }

    /** The titles on this account, or null for the one account that holds every department already. */
    public static function labelsFor(?User $user): ?string
    {
        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        return implode(' · ', self::namesFor((array) ($user->roles ?? []))) ?: null;
    }
}
