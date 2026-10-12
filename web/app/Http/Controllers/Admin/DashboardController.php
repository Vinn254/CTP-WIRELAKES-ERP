<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContentSection;
use App\Models\CtpRegistration;
use App\Models\Donation;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\Partner;
use App\Models\ProjectApplication;
use App\Models\User;

class DashboardController extends Controller
{
    public const DEPARTMENTS = [
        ['slug' => 'membership', 'name' => 'Public Site & Membership', 'module' => 1, 'live' => true, 'desc' => 'Public pages, CTP / Fundi / Hirer applications and approvals'],
        ['slug' => 'promoters', 'name' => 'CTP Promoter Management', 'module' => 2, 'live' => false, 'desc' => 'Approval, tracks, mobilization 15-30, dashboards'],
        ['slug' => 'communities', 'name' => 'Community Management', 'module' => 3, 'live' => false, 'desc' => 'Communities, beneficiaries, needs assessment'],
        ['slug' => 'training', 'name' => 'Training & Certification', 'module' => 4, 'live' => false, 'desc' => 'Courses, cohorts, assessment, certificates'],
        ['slug' => 'efundi', 'name' => 'E-Fundi Management', 'module' => 5, 'live' => false, 'desc' => 'Fundi profiles, tiers, ratings, availability'],
        ['slug' => 'marketplace', 'name' => 'Project Marketplace', 'module' => 6, 'live' => false, 'desc' => 'Project postings and fundi matching'],
        ['slug' => 'execution', 'name' => 'Project Execution & Payment', 'module' => 7, 'live' => false, 'desc' => 'Work orders, progress, payments'],
        ['slug' => 'construction', 'name' => 'Construction & Infrastructure', 'module' => 8, 'live' => false, 'desc' => 'Site assessment, BOQ, inspections'],
        ['slug' => 'technology', 'name' => 'Technology Services', 'module' => 9, 'live' => false, 'desc' => 'ICT, Networking, Robotics, Programming, AI'],
        ['slug' => 'ewaste', 'name' => 'E-Waste Management', 'module' => 10, 'live' => false, 'desc' => 'Collection, weighing, refurbish, disposal'],
        ['slug' => 'finance', 'name' => 'Finance, Procurement & Inventory', 'module' => 11, 'live' => false, 'desc' => 'Budgets, procurement, payments, stock'],
        ['slug' => 'me_gis', 'name' => 'M&E, GIS, Reports & Admin', 'module' => 12, 'live' => false, 'desc' => 'Impact metrics, maps, per-role reports'],
        ['slug' => 'mobile', 'name' => 'Mobile App', 'module' => 13, 'live' => false, 'desc' => 'Field app for CTPs and Fundis'],
    ];

    public function index()
    {
        $pending = [
            'ctp' => CtpRegistration::where('status', 'pending_review')->count(),
            'fundi' => FundiRegistration::where('status', 'pending_review')->count(),
            'hirer' => HirerRegistration::where('status', 'pending_review')->count(),
        ];

        $pendingTotal = array_sum($pending);

        return view('admin.dashboard', [
            'departments' => self::DEPARTMENTS,
            'pending' => $pending,
            'pendingTotal' => $pendingTotal,
            'workspace' => [
                ['route' => ['admin.registrations.index', 'ctp'], 'label' => 'Review & approve applications',
                 'meta' => $pendingTotal.' waiting', 'alert' => $pendingTotal > 0],
                ['route' => ['admin.content.index'], 'label' => 'Site content & pages',
                 'meta' => ContentSection::distinct('page')->count('page').' pages editable'],
                ['route' => ['admin.content.edit', 'membership'], 'label' => 'Membership fees, tracks & tiers',
                 'meta' => 'Feeds the registration forms'],
                ['route' => ['admin.staff.index'], 'label' => 'Staff & roles',
                 'meta' => User::where('active', true)->count().' active staff'],
                ['route' => ['admin.projects.index'], 'label' => 'Projects & assignments',
                 'meta' => ProjectApplication::where('status', 'pending_review')->count().' applications waiting',
                 'alert' => ProjectApplication::where('status', 'pending_review')->count() > 0],
                ['route' => ['admin.hirers.index'], 'label' => 'Hirers & organisations',
                 'meta' => HirerRegistration::where('status', 'active')->count().' recorded'],
                ['route' => ['admin.partners.index'], 'label' => 'Partners',
                 'meta' => Partner::where('is_listed', false)->count().' awaiting listing'],
                ['route' => ['admin.donations.index'], 'label' => 'Donations',
                 'meta' => 'KSh '.number_format((float) Donation::sum('amount')).' pledged'],
                ['route' => ['admin.messages.index'], 'label' => 'Public messages',
                 'meta' => ContactMessage::count().' received'],
            ],
        ]);
    }
}
