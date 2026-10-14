<?php

namespace App\Support;

use App\Models\Certification;
use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Community;
use App\Models\Enrollment;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\Need;
use App\Models\Partner;
use App\Models\ProjectApplication;
use App\Models\Trainer;

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
            'desc' => 'CTP profiles and IDs, tracks, mobilisation 15/30, suspension and deployment', 'live' => true],
        ['slug' => 'communities', 'tag' => 'Module 3', 'name' => 'Community Management',
            'desc' => 'Community profiles and IDs, needs assessment, beneficiaries, the CTPs who mobilise each place', 'live' => true],
        ['slug' => 'training', 'tag' => 'Module 4', 'name' => 'Training & Certification',
            'desc' => 'Partner schools and sites, cohorts CTPs apply for, fundi-trainers, progress, certificates', 'live' => true],
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
        'promoters' => [
            ['label' => 'CTP profiles', 'icon' => 'users', 'route' => ['admin.promoters.index'], 'badge' => 'promoters'],
            ['label' => 'Add CTP from office', 'icon' => 'plus', 'route' => ['admin.promoters.create']],
        ],
        'communities' => [
            ['label' => 'Community register', 'icon' => 'globe', 'route' => ['admin.communities.index'], 'badge' => 'communities'],
            ['label' => 'Needs assessment', 'icon' => 'check', 'route' => ['admin.needs.index'], 'badge' => 'needs'],
            ['label' => 'Beneficiaries', 'icon' => 'users', 'route' => ['admin.beneficiaries.index']],
        ],
        'training' => [
            ['label' => 'Cohorts', 'icon' => 'briefcase', 'route' => ['admin.cohorts.index'], 'badge' => 'seats'],
            ['label' => 'Track', 'icon' => 'check', 'route' => ['admin.track.index']],
            ['label' => 'Training', 'icon' => 'award', 'route' => ['admin.trainings.index']],
            ['label' => 'Training venues', 'icon' => 'building', 'route' => ['admin.venues.index']],
            ['label' => 'Fundi-trainers', 'icon' => 'check', 'route' => ['admin.trainers.index'], 'badge' => 'trainers'],
            ['label' => 'Certification', 'icon' => 'award', 'route' => ['admin.certification.index'], 'badge' => 'certificates'],
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
        'admin.promoters.' => 'promoters',
        'admin.communities.' => 'communities',
        'admin.needs.' => 'communities',
        'admin.beneficiaries.' => 'communities',
        'admin.cohorts.' => 'training',
        'admin.enrollments.' => 'training',
        'admin.track.' => 'training',
        'admin.trainings.' => 'training',
        'admin.venues.' => 'training',
        'admin.trainers.' => 'training',
        'admin.certification.' => 'training',
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
        'admin.promoters.index' => 'Every CTP from application to deployment — profile, track, mobilisation and status',
        'admin.promoters.create' => 'Open a CTP application on someone’s behalf when they cannot reach the website',
        'admin.communities.index' => 'Every community, its CTP, its needs and who benefited there',
        'admin.needs.index' => 'Prioritise what CTPs flagged and turn a need into a project',
        'admin.beneficiaries.index' => 'People, households and businesses a project reached',
        'admin.cohorts.index' => 'Open a training, take seats and track every trainee to certification',
        'admin.track.index' => 'How each CTP is getting on — marks, assessments, modules and uploads up to 100%',
        'admin.trainings.index' => 'The trainings the programme runs, and the modules expected under each',
        'admin.venues.index' => 'Partner schools, community sites and centres that host cohorts',
        'admin.trainers.index' => 'Senior fundis who applied to train CTPs, and what they are paid',
        'admin.certification.index' => 'Approve a certificate and issue its code — or decline and revoke it',
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
            'promoters' => CtpRegistration::whereIn('status', ['pending_payment', 'pending_review'])->count(),
            'applications' => ProjectApplication::where('status', 'pending_review')->count(),
            'communities' => Community::where('status', 'pending_review')->count(),
            'needs' => Need::where('status', 'proposed')->count(),
            'partners' => Partner::where('is_listed', false)->count(),
            'seats' => Enrollment::where('state', 'pending')->count(),
            'trainers' => Trainer::where('status', 'applied')->count(),
            'certificates' => Certification::where('status', 'recommended')->count(),
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
