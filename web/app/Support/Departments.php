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
use App\Models\LeaveRequest;
use App\Models\Need;
use App\Models\Partner;
use App\Models\ProgrammeApplication;
use App\Models\Payout;
use App\Models\PayrollLine;
use App\Models\ProjectApplication;
use App\Models\ProjectMatch;
use App\Models\Requisition;
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
        ['slug' => 'membership', 'name' => 'Website & Membership',
            'desc' => 'Public pages, CTP / E-Fundi applications, member accounts, projects and assignments', 'live' => true],
        ['slug' => 'promoters', 'name' => 'CTP Promoters',
            'desc' => 'CTP profiles and IDs, tracks, mobilisation 15/30, suspension and deployment', 'live' => true],
        ['slug' => 'communities', 'name' => 'Communities',
            'desc' => 'Community profiles and IDs, needs assessment, beneficiaries, the CTPs who mobilise each place', 'live' => true],
        ['slug' => 'training', 'name' => 'Training & Certification',
            'desc' => 'Partner schools and sites, cohorts CTPs apply for, trainers, attendance, progress, certificates', 'live' => true],
        ['slug' => 'efundi', 'name' => 'E-Fundi Registry',
            'desc' => 'E-Fundi profiles and IDs, grades, trade skills, counties, availability and standing', 'live' => true],
        ['slug' => 'marketplace', 'name' => 'Project Marketplace',
            'desc' => 'Match a posted project to the right E-Fundi, ask them, and award the one who takes it', 'live' => true],
        ['slug' => 'execution', 'name' => 'Project Execution',
            'desc' => 'Work orders, materials issued, progress on site, milestones, the hirer\'s inspection and disputes', 'live' => true],
        ['slug' => 'construction', 'name' => 'Construction Works',
            'desc' => 'Site assessment, the bill of quantities, labour on site, inspections and variations', 'live' => true],
        ['slug' => 'technology', 'name' => 'Technology Services',
            'desc' => 'ICT, Networking, Robotics, Programming and AI — the asset register, the projects and the repair tickets', 'live' => true],
        ['slug' => 'ewaste', 'name' => 'E-Waste Collection',
            'desc' => 'Collection, weighing, refurbish, disposal', 'live' => true],
        ['slug' => 'finance', 'name' => 'Finance & Budget',
            'desc' => 'The programme budget, the money register, invoices and the two payout streams', 'live' => true],
        ['slug' => 'me_gis', 'name' => 'Monitoring, Maps & Reports',
            'desc' => 'The impact dashboard, the county map, a report per role, the trail and the programme switches', 'live' => true],
        ['slug' => 'procurement', 'name' => 'Procurement & Stores',
            'desc' => 'Requests, quotations, purchase orders, suppliers and the stock on the shelf', 'live' => true],
        ['slug' => 'hr', 'name' => 'Human Resources',
            'desc' => 'Office accounts and roles, the careers board and the trainers\' signed weeks', 'live' => true],
        ['slug' => 'mobile', 'name' => 'Mobile App',
            'desc' => 'Field app for CTPs and Fundis', 'live' => false],
    ];

    /** The mark a department carries in the office sidebar's icon rail. */
    public const ICONS = [
        'membership' => 'layout', 'promoters' => 'users', 'communities' => 'globe', 'training' => 'award',
        'efundi' => 'hardhat', 'marketplace' => 'link', 'execution' => 'check', 'construction' => 'building',
        'technology' => 'wrench', 'ewaste' => 'recycle', 'finance' => 'briefcase', 'me_gis' => 'map',
        'procurement' => 'clipboard', 'hr' => 'users', 'mobile' => 'image',
    ];

    /** Screens that belong to each live department. */
    private const NAV = [
        'membership' => [
            ['label' => 'Applicants', 'icon' => 'users', 'route' => ['admin.registrations.index', 'ctp'], 'badge' => 'applicants'],
            ['label' => 'Staff accounts & roles', 'icon' => 'key', 'route' => ['admin.staff.index'], 'invite' => true],
            ['label' => 'Site content & pages', 'icon' => 'layout', 'route' => ['admin.content.index']],
            ['label' => 'Membership settings', 'icon' => 'sliders', 'route' => ['admin.content.edit', 'membership']],
            ['label' => 'Projects', 'icon' => 'briefcase', 'route' => ['admin.projects.index'], 'badge' => 'applications'],
            ['label' => 'Hirers', 'icon' => 'building', 'route' => ['admin.hirers.index']],
            ['label' => 'Partners', 'icon' => 'link', 'route' => ['admin.partners.index'], 'badge' => 'partners'],
            ['label' => 'Donations', 'icon' => 'heart', 'route' => ['admin.donations.index']],
            ['label' => 'Public messages', 'icon' => 'mail', 'route' => ['admin.messages.index']],
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],
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
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],

        ],
        'training' => [
            ['label' => 'Cohorts', 'icon' => 'briefcase', 'route' => ['admin.cohorts.index'], 'badge' => 'seats'],
            ['label' => 'Cohort results', 'icon' => 'grid', 'route' => ['admin.cohorts.results']],
            ['label' => 'Track', 'icon' => 'check', 'route' => ['admin.track.index']],
            ['label' => 'Training', 'icon' => 'award', 'route' => ['admin.trainings.index']],
            ['label' => 'Training venues', 'icon' => 'building', 'route' => ['admin.venues.index']],
            ['label' => 'Trainers', 'icon' => 'check', 'route' => ['admin.trainers.index'], 'badge' => 'trainers'],
            ['label' => 'Teaching papers', 'icon' => 'mail', 'route' => ['admin.registers.index'], 'badge' => 'papers'],
            ['label' => 'Library', 'icon' => 'book', 'route' => ['admin.library.index']],
            ['label' => 'Certification', 'icon' => 'award', 'route' => ['admin.certification.index'], 'badge' => 'certificates'],
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],
        ],
        'efundi' => [
            ['label' => 'E-Fundi register', 'icon' => 'users', 'route' => ['admin.fundis.index'], 'badge' => 'fundis'],
        ],
        'marketplace' => [
            ['label' => 'Matching desk', 'icon' => 'briefcase', 'route' => ['admin.marketplace.index'], 'badge' => 'invites'],
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],

        ],
        'execution' => [
            ['label' => 'Delivery register', 'icon' => 'briefcase', 'route' => ['admin.execution.index'], 'badge' => 'workorders'],
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],

        ],
        'construction' => [
            ['label' => 'Construction files', 'icon' => 'briefcase', 'route' => ['admin.construction.index'], 'badge' => 'construction'],
        ],
        'technology' => [
            ['label' => 'Technology registers', 'icon' => 'grid', 'route' => ['admin.technology.index'], 'badge' => 'technology'],
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],

        ],
        'ewaste' => [
            ['label' => 'E-Waste register', 'icon' => 'globe', 'route' => ['admin.ewaste.index'], 'badge' => 'collections'],
            ['label' => 'Service requests', 'icon' => 'users', 'route' => ['admin.applications.index'], 'badge' => 'areaapps'],

        ],
        'finance' => [
            ['label' => 'Programme budget', 'icon' => 'sliders', 'route' => ['admin.budget.index'], 'badge' => 'overbudget'],
            ['label' => 'Money register', 'icon' => 'briefcase', 'route' => ['admin.ledger.index']],
            ['label' => 'Financial reports', 'icon' => 'chart', 'route' => ['admin.statements.index']],
            ['label' => 'Invoices', 'icon' => 'mail', 'route' => ['admin.invoices.index'], 'badge' => 'invoices'],
            ['label' => 'E-Fundi payouts', 'icon' => 'users', 'route' => ['admin.payouts.index'], 'badge' => 'payouts'],
            ['label' => 'Staff payroll', 'icon' => 'briefcase', 'route' => ['admin.staffpay.index'], 'badge' => 'payroll'],
        ],
        'procurement' => [
            ['label' => 'Purchases', 'icon' => 'link', 'route' => ['admin.procurement.index'], 'badge' => 'requests'],
            ['label' => 'Suppliers', 'icon' => 'building', 'route' => ['admin.suppliers.index']],
            ['label' => 'Inventory', 'icon' => 'grid', 'route' => ['admin.inventory.index'], 'badge' => 'lowstock'],
        ],
        'hr' => [
            ['label' => 'Careers & applicants', 'icon' => 'award', 'route' => ['admin.careers.index'], 'badge' => 'careers'],
            ['label' => 'Onboarding', 'icon' => 'check', 'route' => ['admin.onboarding.index'], 'badge' => 'onboarding'],
            ['label' => 'Staff directory', 'icon' => 'users', 'route' => ['admin.directory.index']],
            ['label' => 'Time logs', 'icon' => 'clock', 'route' => ['admin.timelogs.index'], 'badge' => 'timelogs'],
            ['label' => 'Payroll', 'icon' => 'clipboard', 'route' => ['admin.payroll.index'], 'badge' => 'payroll'],
            ['label' => 'Deduction rates', 'icon' => 'sliders', 'route' => ['admin.payroll.rates']],
            ['label' => 'Leave', 'icon' => 'clock', 'route' => ['admin.leave.index'], 'badge' => 'leave'],
            ['label' => 'Staff accounts & roles', 'icon' => 'key', 'route' => ['admin.staff.index'], 'invite' => true],
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
        'admin.applications.' => 'membership',
        'admin.projects.' => 'membership',
        'admin.hirers.' => 'membership',
        'admin.donations.' => 'membership',
        'admin.promoters.' => 'promoters',
        'admin.communities.' => 'communities',
        'admin.needs.' => 'communities',
        'admin.beneficiaries.' => 'communities',
        'admin.cohorts.' => 'training',
        'admin.enrollments.' => 'training',
        'admin.track.' => 'training',
        'admin.students.' => 'training',
        'admin.trainings.' => 'training',
        'admin.venues.' => 'training',
        'admin.trainers.' => 'training',
        'admin.timelogs.' => 'hr',
        'admin.payroll.' => 'hr',
        'admin.registers.' => 'training',
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
        'admin.payouts.' => 'finance',
        'admin.staffpay.' => 'finance',
        'admin.statements.' => 'finance',
        'admin.procurement.' => 'procurement',
        'admin.suppliers.' => 'procurement',
        'admin.inventory.' => 'procurement',
        'admin.impacts.' => 'me_gis',
        'admin.gis.' => 'me_gis',
        'admin.reports.' => 'me_gis',
        'admin.activity.' => 'me_gis',
        'admin.programme.' => 'me_gis',
        'admin.settings.' => 'me_gis',
        // Careers is how the office finds a person, and the accounts office is where the login is made — both are
        // people's records, so they are Human Resources' screens, not Module 1's.
        'admin.careers.' => 'hr',
        'admin.directory.' => 'hr',
        'admin.onboarding.' => 'hr',
        'admin.staff.' => 'hr',
        'admin.leave.' => 'hr',
        // admin.myleave.* deliberately has no owner: a worker's own balance goes with them to every desk they hold.
    ];

    /** Tiles on a department overview page, with the note shown under each. */
    private const NOTES = [
        'admin.registrations.index' => 'Review, approve or reject every application',
        'admin.content.index' => 'Edit the text, images and sections of each public page',
        'admin.content.edit' => 'Fees, CTP tracks and E-Fundi tiers used by the forms',
        'admin.partners.index' => 'Requests from the public page and the partner wall',
        'admin.messages.index' => 'Messages sent from the public contact form',
        'admin.applications.index' => 'Service requests from the public area pages, and what the desk said back',
        'admin.careers.index' => 'Openings the office has put out for a job or project work, and the people who applied',
        'admin.projects.index' => 'Post projects, review applications and assign one worker',
        'admin.hirers.index' => 'Organisations that projects are posted for',
        'admin.donations.index' => 'Every pledge made on the public donate page',
        'admin.staff.index' => 'Invite staff and set their roles',
        'admin.onboarding.index' => 'Applicants given the job, written up as staff with their file',
        'admin.directory.index' => 'Everyone who works here — the C14 next of kin and pay',
        'admin.directory.show' => 'One person’s staff file, kept up to date by this desk',
        'admin.promoters.index' => 'Every CTP from application to deployment — profile, track, mobilisation and status',
        'admin.promoters.create' => 'Open a CTP application on someone’s behalf when they cannot reach the website',
        'admin.communities.index' => 'Every community, its CTP, its needs and who benefited there',
        'admin.needs.index' => 'Prioritise what CTPs flagged and turn a need into a project',
        'admin.beneficiaries.index' => 'People, households and businesses a project reached',
        'admin.cohorts.index' => 'Open a training, take seats and track every trainee to certification',
        'admin.track.index' => 'How each CTP is getting on — marks, assessments, modules and uploads up to 100%',
        'admin.trainings.index' => 'The trainings the programme runs, and the modules expected under each',
        'admin.venues.index' => 'Partner schools, community sites and centres that host cohorts',
        'admin.trainers.index' => 'Who takes each training — an E-Fundi, a staff account or an applicant — and what they are paid',
        'admin.registers.index' => 'What the trainers handed in — registers, marks, timetables and lesson plans waiting to be answered',
        'admin.registers.show' => 'One paper read whole, then approved onto the trainees’ records or sent back with what to fix',
        'admin.library.index' => 'Books, notes, slides and PDFs the students read for each course',
        'admin.timelogs.index' => 'Every week anyone on staff has filled — the days worked, what was done, and Human Resources\' approval for pay',
        'admin.timelogs.week' => 'The signed week as the paper reads: day, clock times, work, initials, hours and units',
        'admin.host.timelogs.index' => 'The same signed weeks, read here — who of the people you work with logged what',
        'admin.host.timelogs.week' => 'The same week sheet, read here as proof of the work before pay',
        'admin.mytime.index' => 'Your own week sheet — pick the month and the week of it, write Monday to Friday, sign the week',
        'admin.payroll.index' => 'Run the month from the staff file — allowances in, every deduction out, then send it to Finance',
        'admin.payroll.show' => 'The month’s sheet: each person, their pay, allowances, deductions and net',
        'admin.payroll.rates' => 'The PAYE bands, relief, NSSF, SHIF and housing levy a month is worked at',
        'admin.staffpay.index' => 'The sheets Human Resources has sent — who is owed what, and what is still to pay',
        'admin.staffpay.show' => 'Pay each person their net; every payment posts its own register line',
        'admin.statements.index' => 'The nine money statements: trial balance, balance sheet, profit and loss, cashflow, ledger, ageing, payroll and the trail',
        'admin.statements.show' => 'One statement, counted again from the registers the moment it is opened',
        'admin.leave.index' => 'Every application on the register — approve it or send it back with what to fix',
        'admin.host.leave.index' => 'The leave the people of this desk hold, read here',
        'admin.myleave.index' => 'Your balances, the days left, and an application to Human Resources',
        'admin.certification.index' => 'Approve a certificate and issue its code — or decline and revoke it',
        'admin.fundis.index' => 'Every E-Fundi: grade, trade skills, counties covered, availability and standing',
        'admin.marketplace.index' => 'Run the search, put the project to the top of the shortlist, and award it to a fundi who took it',
        'admin.execution.index' => 'The order an award turns into: materials issued, days on site, stages signed off, the hirer\'s inspection and any dispute',
        'admin.construction.index' => 'The engineering file behind a build: the site read, the priced BoQ, labour counted, inspections re-checked and variations decided',
        'admin.technology.index' => 'The five sub-areas on one desk: every device and licence on the asset register, the robotics/software/AI projects, and the repair tickets the fundis answer',
        'admin.ewaste.index' => 'What the field collected, what it weighed in at and what condition it is in',
        'admin.budget.index' => 'What each programme category was given this year, and what has been charged against it',
        'admin.ledger.index' => 'Every shilling in and out, with the budget line it sits on',
        'admin.invoices.index' => 'Money the programme asks for — donors, CTPs and E-Fundis billed, what came in against each invoice',
        'admin.procurement.index' => 'Requests, quotations, purchase orders, deliveries and payments — one path per request',
        'admin.requisitions.index' => 'What each desk is asking for — Procurement approves it, Finance pays it out or hands the goods over',
        'admin.requisitions.show' => 'One requisition and its decision, from the ask to the money or the goods',
        'admin.suppliers.index' => 'The suppliers quotations are taken from, and who has already been paid',
        'admin.inventory.index' => 'Stock cards by SKU, what is on the shelf, what is below its reorder level and what left the store',
        'admin.payouts.index' => 'Project income and training income, kept on two separate release paths',
        'admin.impacts.index' => 'The five things the programme measures, counted from the registers the other modules write',
        'admin.gis.map' => 'One square per county, shaded by the layer you pick, opening the records held there',
        'admin.reports.index' => 'The report each role works from, printed or exported as a file',
        'admin.activity.index' => 'Who changed what, when — written by the records themselves, never typed in',
        'admin.host.activity.index' => 'The same trail, read here: every sign-in, code, decision and record change in the programme',
        'admin.programme.index' => 'The Paybill, contact details and payment amounts the code reads; an executive changes them',
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
        // Human Resources approves the week; every other desk reads the very same sheet as its own copy — Training to
        // see who is on a site, Finance as the work paid for, the rest to know what their people logged.
        'admin.host.timelogs.index' => ['admin.timelogs.index', ['membership', 'promoters', 'communities', 'efundi',
            'training', 'marketplace', 'execution', 'construction', 'technology', 'ewaste', 'finance', 'me_gis', 'procurement']],
        'admin.host.timelogs.week' => ['admin.timelogs.week', ['membership', 'promoters', 'communities', 'efundi',
            'training', 'marketplace', 'execution', 'construction', 'technology', 'ewaste', 'finance', 'me_gis', 'procurement']],
        // Human Resources answers a leave application; each desk sees the ones its own people hold, read here.
        'admin.host.leave.index' => ['admin.leave.index', ['membership', 'promoters', 'communities', 'training', 'efundi',
            'marketplace', 'execution', 'construction', 'technology', 'ewaste', 'finance', 'me_gis', 'procurement']],
        // Website & Membership keeps the public intake register; the desk running an area reads the lines that
        // came in on its own page here, and answers them from the record it is pointed to by email.
        'admin.host.applications.index' => ['admin.applications.index', ['training', 'marketplace', 'execution',
            'ewaste', 'communities', 'technology']],
        'admin.host.applications.show' => ['admin.applications.show', ['training', 'marketplace', 'execution',
            'ewaste', 'communities', 'technology']],
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function find(string $slug): ?array
    {
        return collect(self::ALL)->firstWhere('slug', $slug);
    }

    public static function icon(string $slug): string
    {
        return self::ICONS[$slug] ?? 'grid';
    }

    /** The departments open today — a Phase-2 module is not on any staff screen. */
    public static function live(): array
    {
        return array_values(array_filter(self::ALL, fn (array $d) => $d['live']));
    }

    /** slug => name, for a filter or a "which desk is asking" select. */
    public static function nameList(?array $slugs = null): array
    {
        $live = collect(self::live());

        if ($slugs !== null) {
            $live = $live->whereIn('slug', $slugs);
        }

        return $live->pluck('name', 'slug')->all();
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

    /**
     * Who runs the staff accounts desk — the one screen that makes and un-makes an office login. Human Resources
     * hires into the programme, so it keeps this desk beside the executive. A System Administrator runs the website
     * and the reports, not the people, so the title no longer opens it.
     */
    public static function mayInvite(?User $user): bool
    {
        return $user !== null && ($user->isSuperAdmin()
            || in_array('hr', $user->departmentSlugs(), true));
    }

    /**
     * The live office accounts running one department — the people a public application in that department's area
     * is announced to, next to the executive. A title that later takes on the tick brings its holder in without
     * anyone re-saving the account.
     *
     * @return array<int, string>
     */
    public static function staffEmails(?string $slug): array
    {
        if (! $slug) {
            return [];
        }

        return User::query()->where('active', true)->whereNotNull('email')
            ->get(['email', 'departments', 'roles', 'role'])
            ->reject(fn (User $user) => $user->isSuperAdmin()) // the executive is already on the notice
            ->filter(fn (User $user) => in_array($slug, $user->departmentSlugs(), true))
            ->pluck('email')->all();
    }

    /**
     * The public area pages whose applications this account answers. Website & Membership and the executive read the
     * whole intake; every other desk reads the areas of work it actually runs. Null means every area.
     *
     * @return array<int, string>|null
     */
    public static function areasFor(?User $user): ?array
    {
        $slugs = static::visibleSlugs($user);

        if (in_array('membership', $slugs, true)) {
            return null;
        }

        return array_keys(array_filter(ProgrammeAreas::AREAS,
            fn (array $area) => in_array($area['department'], $slugs, true)));
    }

    /** Lines from the area pages that are still waiting to be read by this desk. */
    public static function waitingAreaApplications(?User $user): int
    {
        $areas = static::areasFor($user);

        return ProgrammeApplication::where('status', 'received')
            ->when($areas !== null, fn ($q) => $q->whereIn('area', $areas ?: ['__none__']))
            ->count();
    }

    /**
     * The screens of a department. Two registers are copied into every desk — the signed weeks and the leave the
     * desk's own people hold — so reading either never moves someone out of the department they work in, and every
     * worker fills their own week sheet and books their own leave from whichever desk they are standing at.
     */
    public static function nav(string $slug): array
    {
        // The invite desk sits with the people it hires as well as with Human Resources, and only where the signed-in
        // account actually runs it — a tile that would answer 403 is never put in front of someone.
        $items = array_values(array_filter(self::NAV[$slug] ?? [],
            fn (array $i) => empty($i['invite']) || static::mayInvite(auth()->user())));

        // Every desk asks for what it needs to work. Procurement checks and approves, Finance processes — the same
        // register, read from each side.
        $items[] = ['label' => 'Requisitions', 'icon' => 'clipboard',
            'route' => ['admin.requisitions.index'], 'badge' => 'requisitions'];

        if ($slug !== 'hr') {
            $items[] = ['label' => 'Time logs', 'icon' => 'clock',
                'route' => ['admin.host.timelogs.index', $slug], 'badge' => 'timelogs'];
        }

        if ($slug !== 'hr') {
            $items[] = ['label' => 'Leave', 'icon' => 'check', 'route' => ['admin.host.leave.index', $slug]];
        }

        $items[] = ['label' => 'My time log', 'icon' => 'clock', 'route' => ['admin.mytime.index']];
        $items[] = ['label' => 'My leave', 'icon' => 'mail', 'route' => ['admin.myleave.index']];

        return $items;
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
            'areaapps' => static::waitingAreaApplications(auth()->user()),
            'onboarding' => JobApplication::whereIn('status', JobApplication::ONBOARDABLE)->count(),
            'seats' => Enrollment::where('state', 'pending')->count(),
            'trainers' => Trainer::where('status', 'applied')->count(),
            'papers' => TeachingDesk::pending(),
            'timelogs' => TimeSheet::waitingFor(auth()->user()),
            'leave' => LeaveRequest::where('status', 'pending')->count(),
            'certificates' => Certification::where('status', 'recommended')->count(),
            'fundis' => FundiRegistration::whereIn('status', ['pending_payment', 'pending_review'])->count(),
            'invites' => ProjectMatch::where('status', 'invited')->count(),
            'workorders' => WorkOrder::whereIn('status', ['issued', 'completion_submitted', 'disputed', 'confirmed'])->count(),
            'construction' => Construction::badge(),
            'technology' => Technology::badge(),
            'collections' => EWasteCollection::whereIn('status', ['submitted', 'forwarded'])->count(),
            'requests' => PurchaseRequest::whereIn('status', ['submitted', 'quoted'])->count(),
            'requisitions' => Requisition::whereIn('status', ['submitted', 'approved'])->count(),
            'orders' => PurchaseOrder::whereIn('status', ['received', 'matched'])->count(),
            'lowstock' => InventoryItem::where('status', 'active')->whereColumn('reorder_level', '>', 'qty_on_hand')->count(),
            'overbudget' => BudgetLine::where('fiscal_year', now()->year)->where('amount_ksh', '>', 0)->get()
                ->filter(fn (BudgetLine $line) => FinanceEntry::spent($line->fiscal_year, $line->category) > (float) $line->amount_ksh)->count(),
            'payouts' => Payout::whereIn('stage', ['queued', 'approved'])->count(),
            'payroll' => PayrollLine::where('status', 'unpaid')
                ->whereHas('run', fn ($q) => $q->where('status', 'sent'))->count(),
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
