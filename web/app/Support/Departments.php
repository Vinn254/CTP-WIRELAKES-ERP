<?php

namespace App\Support;

use App\Models\BudgetLine;
use App\Models\Certification;
use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Community;
use App\Models\Enrollment;
use App\Models\EWasteCollection;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\Need;
use App\Models\Partner;
use App\Models\Payout;
use App\Models\ProjectApplication;
use App\Models\ProjectMatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Trainer;
use App\Models\User;
use App\Models\WorkOrder;

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
            'desc' => 'E-Fundi profiles and IDs, grades, trade skills, counties, availability and standing', 'live' => true],
        ['slug' => 'marketplace', 'tag' => 'Module 6', 'name' => 'Project Marketplace',
            'desc' => 'Match a posted project to the right E-Fundi, ask them, and award the one who takes it', 'live' => true],
        ['slug' => 'execution', 'tag' => 'Module 7', 'name' => 'Project Execution & Payment',
            'desc' => 'Work orders, materials issued, progress on site, milestones, the hirer\'s inspection and disputes', 'live' => true],
        ['slug' => 'construction', 'tag' => 'Module 8', 'name' => 'Construction & Infrastructure',
            'desc' => 'Site assessment, the bill of quantities, labour on site, inspections and variations', 'live' => true],
        ['slug' => 'technology', 'tag' => 'Module 9', 'name' => 'Technology Services',
            'desc' => 'ICT, Networking, Robotics, Programming and AI — the asset register, the projects and the repair tickets', 'live' => true],
        ['slug' => 'ewaste', 'tag' => 'Module 10', 'name' => 'E-Waste Management',
            'desc' => 'Collection, weighing, refurbish, disposal', 'live' => true],
        ['slug' => 'finance', 'tag' => 'Module 11', 'name' => 'Finance, Procurement & Inventory',
            'desc' => 'Budgets, the money register, purchases, stock and the two payout streams', 'live' => true],
        ['slug' => 'me_gis', 'tag' => 'Module 12', 'name' => 'M&E, GIS, Reports',
            'desc' => 'The impact dashboard, the county map, a report per role, the trail and the programme switches', 'live' => true],
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
            ['label' => 'Careers', 'icon' => 'award', 'route' => ['admin.careers.index'], 'badge' => 'careers'],
            ['label' => 'Staff & roles', 'icon' => 'key', 'route' => ['admin.staff.index']],
            ['label' => 'Activity trail', 'icon' => 'check', 'route' => ['admin.host.activity.index', 'membership']],
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
            ['label' => 'Cohort results', 'icon' => 'grid', 'route' => ['admin.cohorts.results']],
            ['label' => 'Track', 'icon' => 'check', 'route' => ['admin.track.index']],
            ['label' => 'Training', 'icon' => 'award', 'route' => ['admin.trainings.index']],
            ['label' => 'Training venues', 'icon' => 'building', 'route' => ['admin.venues.index']],
            ['label' => 'Fundi-trainers', 'icon' => 'check', 'route' => ['admin.trainers.index'], 'badge' => 'trainers'],
            ['label' => 'Certification', 'icon' => 'award', 'route' => ['admin.certification.index'], 'badge' => 'certificates'],
        ],
        'efundi' => [
            ['label' => 'E-Fundi register', 'icon' => 'users', 'route' => ['admin.fundis.index'], 'badge' => 'fundis'],
        ],
        'marketplace' => [
            ['label' => 'Matching desk', 'icon' => 'briefcase', 'route' => ['admin.marketplace.index'], 'badge' => 'invites'],
        ],
        'execution' => [
            ['label' => 'Delivery register', 'icon' => 'briefcase', 'route' => ['admin.execution.index'], 'badge' => 'workorders'],
        ],
        'construction' => [
            ['label' => 'Construction files', 'icon' => 'briefcase', 'route' => ['admin.construction.index'], 'badge' => 'construction'],
        ],
        'technology' => [
            ['label' => 'Technology registers', 'icon' => 'grid', 'route' => ['admin.technology.index'], 'badge' => 'technology'],
        ],
        'ewaste' => [
            ['label' => 'E-Waste register', 'icon' => 'globe', 'route' => ['admin.ewaste.index'], 'badge' => 'collections'],
        ],
        'finance' => [
            ['label' => 'Programme budget', 'icon' => 'sliders', 'route' => ['admin.budget.index'], 'badge' => 'overbudget'],
            ['label' => 'Money register', 'icon' => 'briefcase', 'route' => ['admin.ledger.index']],
            ['label' => 'Invoices', 'icon' => 'mail', 'route' => ['admin.invoices.index'], 'badge' => 'invoices'],
            ['label' => 'Purchases', 'icon' => 'link', 'route' => ['admin.procurement.index'], 'badge' => 'requests'],
            ['label' => 'Suppliers', 'icon' => 'building', 'route' => ['admin.suppliers.index']],
            ['label' => 'Inventory', 'icon' => 'grid', 'route' => ['admin.inventory.index'], 'badge' => 'lowstock'],
            ['label' => 'E-Fundi payouts', 'icon' => 'users', 'route' => ['admin.payouts.index'], 'badge' => 'payouts'],
        ],
        'me_gis' => [
            ['label' => 'Impact dashboard', 'icon' => 'grid', 'route' => ['admin.impacts.index']],
            ['label' => 'Programme map', 'icon' => 'globe', 'route' => ['admin.gis.map']],
            ['label' => 'Reports', 'icon' => 'briefcase', 'route' => ['admin.reports.index']],
            ['label' => 'Activity trail', 'icon' => 'check', 'route' => ['admin.activity.index']],
            ['label' => 'Programme settings', 'icon' => 'sliders', 'route' => ['admin.programme.index']],
        ],
    ];

    /** Which department owns a route name prefix. */
    private const OWNERS = [
        'admin.registrations.' => 'membership',
        'admin.content.' => 'membership',
        'admin.partners.' => 'membership',
        'admin.messages.' => 'membership',
        'admin.careers.' => 'membership',
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
        'admin.fundis.' => 'efundi',
        'admin.marketplace.' => 'marketplace',
        'admin.execution.' => 'execution',
        'admin.construction.' => 'construction',
        'admin.technology.' => 'technology',
        'admin.ewaste.' => 'ewaste',
        'admin.budget.' => 'finance',
        'admin.ledger.' => 'finance',
        'admin.invoices.' => 'finance',
        'admin.procurement.' => 'finance',
        'admin.suppliers.' => 'finance',
        'admin.inventory.' => 'finance',
        'admin.payouts.' => 'finance',
        'admin.impacts.' => 'me_gis',
        'admin.gis.' => 'me_gis',
        'admin.reports.' => 'me_gis',
        'admin.activity.' => 'me_gis',
        'admin.programme.' => 'me_gis',
        'admin.settings.' => 'me_gis',
    ];

    /** Tiles on a department overview page, with the note shown under each. */
    private const NOTES = [
        'admin.registrations.index' => 'Review, approve or reject every application',
        'admin.content.index' => 'Edit the text, images and sections of each public page',
        'admin.content.edit' => 'Fees, CTP tracks and E-Fundi tiers used by the forms',
        'admin.partners.index' => 'Requests from the public page and the partner wall',
        'admin.messages.index' => 'Messages sent from the public contact form',
        'admin.careers.index' => 'Openings the office has put out for a job or project work, and the people who applied',
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
        'admin.fundis.index' => 'Every E-Fundi: grade, trade skills, counties covered, availability and standing',
        'admin.marketplace.index' => 'Run the search, put the project to the top of the shortlist, and award it to a fundi who took it (§9.4)',
        'admin.execution.index' => 'The order an award turns into: materials issued, days on site, stages signed off, the hirer\'s inspection and any dispute (§10.4)',
        'admin.construction.index' => 'The engineering file behind a build: the site read, the priced BoQ, labour counted, inspections re-checked and variations decided (§11)',
        'admin.technology.index' => 'The five sub-areas on one desk: every device and licence on the asset register, the robotics/software/AI projects, and the repair tickets the fundis answer (§12)',
        'admin.ewaste.index' => 'What the field collected, what it weighed in at and what condition it is in',
        'admin.budget.index' => 'What each programme category was given this year, and what has been charged against it',
        'admin.ledger.index' => 'Every shilling in and out, with the budget line it sits on',
        'admin.invoices.index' => 'Money the programme asks for — donors, CTPs and E-Fundis billed, what came in against each invoice',
        'admin.procurement.index' => 'Requests, quotations, purchase orders, deliveries and payments — one path per request',
        'admin.suppliers.index' => 'The suppliers quotations are taken from, and who has already been paid',
        'admin.inventory.index' => 'Stock cards by SKU, what is on the shelf, what is below its reorder level and what left the store',
        'admin.payouts.index' => 'Project income and training income, kept on two separate release paths (§14.6)',
        'admin.impacts.index' => 'The five things the programme measures, counted from the registers the other modules write (§15.2)',
        'admin.gis.map' => 'One square per county, shaded by the layer you pick, opening the records held there (§15.4)',
        'admin.reports.index' => 'The report each role works from, printed or exported as a file (§15.5, §15.6)',
        'admin.activity.index' => 'Who changed what, when — written by the records themselves, never typed in (§15.6)',
        'admin.host.activity.index' => 'The same trail, read here: every sign-in, code, decision and record change in the programme',
        'admin.programme.index' => 'The membership fees and contact details the code reads; an executive changes them (§15.6)',
    ];

    /**
     * Shared records. A department whose own screen links to another module's record is given a copy of that screen
     * inside its office — same controller, same data, its URL carrying the department it is shown in. Reading a
     * record never moves anyone out of the department they work in. The owning module keeps every write action: a
     * copy is read-only, and the routes that change the record stay where they belong.
     *
     * hosted route name => [the screen it copies, the departments given a copy of it]
     */
    public const HOSTED = [
        'admin.host.projects.show' => ['admin.projects.show', ['marketplace', 'execution', 'construction', 'communities', 'promoters', 'efundi']],
        'admin.host.projects.index' => ['admin.projects.index', ['marketplace', 'execution', 'construction']],
        'admin.host.promoters.show' => ['admin.promoters.show', ['membership', 'communities', 'training']],
        'admin.host.communities.show' => ['admin.communities.show', ['membership', 'training', 'marketplace', 'execution', 'construction']],
        'admin.host.fundis.show' => ['admin.fundis.show', ['marketplace', 'execution', 'training']],
        'admin.host.inventory.show' => ['admin.inventory.show', ['membership', 'marketplace', 'execution', 'construction']],
        'admin.host.cohorts.show' => ['admin.cohorts.show', ['communities']],
        'admin.host.payouts.show' => ['admin.payouts.show', ['execution', 'construction']],
        'admin.host.execution.show' => ['admin.execution.show', ['construction']],
        // Module 1 keeps the accounts and the people who hold them, so it reads the trail too — as its own copy of
        // the M&E screen, never a link out of the department.
        'admin.host.activity.index' => ['admin.activity.index', ['membership']],
        'admin.host.activity.export' => ['admin.activity.export', ['membership']],
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function find(string $slug): ?array
    {
        return collect(self::ALL)->firstWhere('slug', $slug);
    }

    /** The departments open today — a Phase-2 module is not on any staff screen. */
    public static function live(): array
    {
        return array_values(array_filter(self::ALL, fn (array $d) => $d['live']));
    }

    /** The module slugs one staff account may reach. A super admin gets every live one; an admin gets what they are
     *  assigned; anyone else (a member, an unscoped account) gets none. Never assumed — granted. */
    public static function visibleSlugs(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $live = array_column(self::live(), 'slug');

        return $user->isSuperAdmin()
            ? $live
            : array_values(array_intersect($user->departmentSlugs(), $live));
    }

    /** The department cards a signed-in staff member is allowed to see. */
    public static function visibleFor(?User $user): array
    {
        $slugs = static::visibleSlugs($user);

        return array_values(array_filter(self::live(), fn (array $d) => in_array($d['slug'], $slugs, true)));
    }

    public static function canSee(?User $user, ?string $slug): bool
    {
        return $slug !== null && in_array($slug, static::visibleSlugs($user), true);
    }

    public static function nav(string $slug): array
    {
        return self::NAV[$slug] ?? [];
    }

    /**
     * Screens only a super admin may truly use, wherever they sit. The accounts office lives in Module 1, so it
     * would otherwise appear in a System Administrator's list and answer 403 — inviting staff and changing roles
     * stays with the one account that holds every module.
     */
    private const SUPER_ONLY = ['admin.staff.index'];

    /** The screens of a department this staff account can actually open. */
    public static function mine(?User $user, array $items): array
    {
        if ($user && $user->isSuperAdmin()) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn (array $item) => ! in_array($item['route'][0], self::SUPER_ONLY, true)
        ));
    }

    /** The line shown under a screen on an overview page. */
    public static function note(string $routeName): string
    {
        return self::NOTES[$routeName] ?? '';
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

    /** The department a screen is being shown in — its own copy of a shared record, or the module that owns it. */
    public static function currentSlug(): ?string
    {
        $route = app('router')->getCurrentRoute();

        return $route?->parameter('department') ?: static::owner($route?->getName());
    }

    /** Every department that is given copies of shared records. */
    public static function hosts(): array
    {
        return array_values(array_unique(array_merge(...array_column(self::HOSTED, 1))));
    }

    /** Are we standing in another module's record, shown here as a read-only copy? */
    public static function hosted(?string $routeName = null): bool
    {
        return in_array($routeName ?: static::currentRouteName(), array_keys(self::HOSTED), true);
    }

    private static function currentRouteName(): ?string
    {
        return app('router')->getCurrentRoute()?->getName();
    }

    /** Is this department given a copy of this shared screen? The only way a host reaches another module's record. */
    public static function canHost(string $hostedRouteName, ?string $host): bool
    {
        return $host !== null && in_array($host, self::HOSTED[$hostedRouteName][1] ?? [], true);
    }

    /**
     * A link that stays inside the department you are in: if this department is given a copy of the screen, open the
     * copy; otherwise open the owning module's. Use it for every link to another department's record.
     */
    public static function inside(string $routeName, array $params = []): string
    {
        $copy = static::copyOf($routeName);

        return $copy
            ? route($copy, $params + ['department' => static::currentSlug()])
            : route($routeName, $params);
    }

    /** The hosted route this department is given for another module's screen, if it is given one. */
    public static function copyOf(string $owningRouteName): ?string
    {
        $host = static::currentSlug();

        if ($host === null) {
            return null;
        }

        foreach (self::HOSTED as $hostedName => [$owning, $hosts]) {
            if ($owning === $owningRouteName && in_array($host, $hosts, true)) {
                return $hostedName;
            }
        }

        return null;
    }

    /** Can this staff member open the screen at all — as their own copy, or in the module that owns it? */
    public static function reachable(?User $user, string $routeName): bool
    {
        return static::copyOf($routeName) !== null || static::canSee($user, static::owner($routeName));
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
            'careers' => JobApplication::where('status', 'received')->count(),
            'seats' => Enrollment::where('state', 'pending')->count(),
            'trainers' => Trainer::where('status', 'applied')->count(),
            'certificates' => Certification::where('status', 'recommended')->count(),
            'fundis' => FundiRegistration::whereIn('status', ['pending_payment', 'pending_review'])->count(),
            'invites' => ProjectMatch::where('status', 'invited')->count(),
            'workorders' => WorkOrder::whereIn('status', ['issued', 'completion_submitted', 'disputed', 'confirmed'])->count(),
            'construction' => Construction::badge(),
            'technology' => Technology::badge(),
            'collections' => EWasteCollection::whereIn('status', ['submitted', 'forwarded'])->count(),
            'requests' => PurchaseRequest::whereIn('status', ['submitted', 'quoted'])->count(),
            'orders' => PurchaseOrder::whereIn('status', ['received', 'matched'])->count(),
            'lowstock' => InventoryItem::where('status', 'active')->whereColumn('reorder_level', '>', 'qty_on_hand')->count(),
            'overbudget' => BudgetLine::where('fiscal_year', now()->year)->where('amount_ksh', '>', 0)->get()
                ->filter(fn (BudgetLine $line) => FinanceEntry::spent($line->fiscal_year, $line->category) > (float) $line->amount_ksh)->count(),
            'payouts' => Payout::whereIn('stage', ['queued', 'approved'])->count(),
            'invoices' => Invoice::where('status', 'open')->count(),
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
