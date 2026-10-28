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
            'desc' => 'Website & Membership · Human Resources · Marketplace · Execution · Construction · Technology · E-Waste · Reports',
            'departments' => ['membership', 'hr', 'marketplace', 'execution', 'construction', 'technology', 'ewaste', 'me_gis'],
        ],
        'academic_registrar' => [
            'name' => 'Academic Registrar',
            'desc' => 'CTP Promoters · Communities · Training & Certification · E-Fundi Registry',
            'departments' => ['promoters', 'communities', 'training', 'efundi'],
        ],
        'finance_officer' => [
            'name' => 'Finance Officer',
            'desc' => 'Finance & Budget, with Procurement & Stores in view',
            'departments' => ['finance', 'procurement'],
        ],
        'procurement_officer' => [
            'name' => 'Procurement Officer',
            'desc' => 'Procurement & Stores — requests, suppliers, orders and stock',
            'departments' => ['procurement'],
        ],
        'hr_officer' => [
            'name' => 'Human Resources Officer',
            'desc' => 'Human Resources — careers board, applicants and signed weeks — with Membership in view',
            'departments' => ['hr', 'membership'],
        ],
        'projects_desk' => [
            'name' => 'Projects Officer',
            'desc' => 'Marketplace · Execution · Construction · Technology · E-Waste',
            'departments' => ['marketplace', 'execution', 'construction', 'technology', 'ewaste'],
        ],
        // The one title that opens no module: it is a person's own teaching record, not a department's desk.
        'trainer' => [
            'name' => 'Trainer',
            'desc' => 'The cohorts you take, the marks you record, the register you mark and the week you sign',
            'departments' => [],
        ],
    ];

    /** @return array<string, array{name: string, desc: string, departments: array<int, string>}> */
    public static function all(): array
    {
        return self::PROFILES;
    }

    /** Titles that open a personal desk rather than a department, so they grant no module ticks. */
    public static function personal(string $slug): bool
    {
        return ($profile = self::PROFILES[$slug] ?? null) ? $profile['departments'] === [] : false;
    }

    /** Does this set of titles open anything at all — a module or a personal desk? */
    public static function grants(array $roleSlugs): bool
    {
        return self::departmentsFor($roleSlugs) !== []
            || (bool) array_filter($roleSlugs, fn ($slug) => self::personal($slug));
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
