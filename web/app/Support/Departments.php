<?php

namespace App\Support;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\Partner;
use App\Models\ProjectApplication;

/**
 * Every department (module) of the ERP, the screens that belong to it, and the
 * live queue counts shown on its card and sidebar entry.
 */
class Departments
{
    public const ALL = [
        ['slug' => 'membership', 'tag' => 'Module 1', 'name' => 'Public Site & Membership',
            'desc' => 'Public pages, CTP / E-Fundi applications, member accounts, projects and assignments', 'live' => true],
        ['slug' => 'promoters', 'tag' => 'Module 2', 'name' => 'CTP Promoter Management',
            'desc' => 'Approval, tracks, mobilization 15-30, dashboards', 'live' => false],
        ['slug' => 'communities', 'tag' => 'Module 3', 'name' => 'Community Management',
            'desc' => 'Communities, beneficiaries, needs assessment', 'live' => false],
        ['slug' => 'training', 'tag' => 'Module 4', 'name' => 'Training & Certification',
            'desc' => 'Courses, cohorts, assessment, certificates', 'live' => false],
        ['slug' => 'efundi', 'tag' => 'Module 5', 'name' => 'E-Fundi Management',
            'desc' => 'Fundi profiles, tiers, ratings, availability', 'live' => false],
        ['slug' => 'marketplace', 'tag' => 'Module 6', 'name' => 'Project Marketplace',
            'desc' => 'Automated matching, bids and award recommendations', 'live' => false],
        ['slug' => 'execution', 'tag' => 'Module 7', 'name' => 'Project Execution & Payment',
            'desc' => 'Work orders, progress, payments', 'live' => false],
        ['slug' => 'construction', 'tag' => 'Module 8', 'name' => 'Construction & Infrastructure',
            'desc' => 'Site assessment, BOQ, inspections', 'live' => false],
        ['slug' => 'technology', 'tag' => 'Module 9', 'name' => 'Technology Services',
            'desc' => 'ICT, Networking, Robotics, Programming, AI', 'live' => false],
        ['slug' => 'ewaste', 'tag' => 'Module 10', 'name' => 'E-Waste Management',
            'desc' => 'Collection, weighing, refurbish, disposal', 'live' => false],
        ['slug' => 'finance', 'tag' => 'Module 11', 'name' => 'Finance, Procurement & Inventory',
            'desc' => 'Budgets, procurement, payments, stock', 'live' => false],
        ['slug' => 'me_gis', 'tag' => 'Module 12', 'name' => 'M&E, GIS, Reports',
            'desc' => 'Impact metrics, maps, per-role reports', 'live' => false],
        ['slug' => 'mobile', 'tag' => 'Module 13', 'name' => 'Mobile App',
            'desc' => 'Field app for CTPs and Fundis', 'live' => false],
    ];

    /** Screens that belong to each live department. */
    private const NAV = [
        'membership' => [
            ['label' => 'Applicants', 'icon' => 'users', 'route' => ['admin.registrations.index', 'ctp'], 'badge' => 'applicants'],
            ['label' => 'Site content & pages', 'icon' => 'layout', 'route' => ['admin.content.index']],
            ['label' => 'Membership settings', 'icon' => 'sliders', 'route' => ['admin.content.edit', 'membership']],
            ['label' => 'Projects', 'icon' => 'briefcase', 'route' => ['admin.projects.index'], 'badge' => 'applications'],
            ['label' => 'Hirers', 'icon' => 'building', 'route' => ['admin.hirers.index']],
            ['label' => 'Partners', 'icon' => 'link', 'route' => ['admin.partners.index'], 'badge' => 'partners'],
            ['label' => 'Donations', 'icon' => 'heart', 'route' => ['admin.donations.index']],
            ['label' => 'Public messages', 'icon' => 'mail', 'route' => ['admin.messages.index']],
            ['label' => 'Staff & roles', 'icon' => 'key', 'route' => ['admin.staff.index']],
        ],
    ];

    /** Which department owns a route name prefix. */
    private const OWNERS = [
        'admin.registrations.' => 'membership',
        'admin.content.' => 'membership',
        'admin.partners.' => 'membership',
        'admin.messages.' => 'membership',
        'admin.projects.' => 'membership',
        'admin.hirers.' => 'membership',
        'admin.donations.' => 'membership',
        'admin.staff.' => 'membership',
    ];

    /** Tiles on a department overview page, with the note shown under each. */
    private const NOTES = [
        'admin.registrations.index' => 'Review, approve or reject every application',
        'admin.content.index' => 'Edit the text, images and sections of each public page',
        'admin.content.edit' => 'Fees, CTP tracks and E-Fundi tiers used by the forms',
        'admin.partners.index' => 'Requests from the public page and the partner wall',
        'admin.messages.index' => 'Messages sent from the public contact form',
        'admin.projects.index' => 'Post projects, review applications and assign one worker',
        'admin.hirers.index' => 'Organisations that projects are posted for',
        'admin.donations.index' => 'Every pledge made on the public donate page',
        'admin.staff.index' => 'Invite staff and set their roles',
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function find(string $slug): ?array
    {
        return collect(self::ALL)->firstWhere('slug', $slug);
    }

    public static function nav(string $slug): array
    {
        return self::NAV[$slug] ?? [];
    }

    public static function owner(?string $routeName): ?string
    {
        foreach (self::OWNERS as $prefix => $slug) {
            if ($routeName && str_starts_with($routeName, $prefix)) {
                return $slug;
            }
        }

        return null;
    }

    /** Overview tiles for a department — the same screens as its sidebar. */
    public static function tiles(string $slug): array
    {
        return array_map(function (array $item) {
            return $item + ['note' => self::NOTES[$item['route'][0]] ?? ''];
        }, self::nav($slug));
    }

    public static function badge(?string $key): int
    {
        return match ($key) {
            'applicants' => static::pendingApplications(),
            'applications' => ProjectApplication::where('status', 'pending_review')->count(),
            'partners' => Partner::where('is_listed', false)->count(),
            default => 0,
        };
    }

    public static function pendingApplications(): int
    {
        return CtpRegistration::where('status', 'pending_review')->count()
            + FundiRegistration::where('status', 'pending_review')->count()
            + HirerRegistration::where('status', 'pending_review')->count();
    }

    /** Waiting queue shown on a department card. */
    public static function waiting(string $slug): int
    {
        return array_sum(array_map(
            fn (array $item) => static::badge($item['badge'] ?? null),
            self::nav($slug)
        ));
    }
}
