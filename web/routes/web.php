<?php

use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HirerController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RecordsController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\ActivateController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/what-we-do', [PageController::class, 'whatWeDo'])->name('what-we-do');
Route::get('/partners', [PageController::class, 'partners'])->name('partners');
Route::post('/partners', [PageController::class, 'storePartner'])->name('partners.store');
Route::get('/donate', [PageController::class, 'donate'])->name('donate');
Route::post('/donate', [PageController::class, 'storeDonation'])->name('donate.store');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'storeContact'])->name('contact.store');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');

// Registrations
Route::prefix('register')->name('register.')->group(function () {
    Route::get('/ctp', [RegistrationController::class, 'ctpForm'])->name('ctp');
    Route::post('/ctp', [RegistrationController::class, 'storeCtp'])->name('ctp.store');
    Route::get('/fundi', [RegistrationController::class, 'fundiForm'])->name('fundi');
    Route::post('/fundi', [RegistrationController::class, 'storeFundi'])->name('fundi.store');
    Route::get('/{type}/success/{reference}', [RegistrationController::class, 'success'])
        ->whereIn('type', ['ctp', 'fundi'])->name('success');
});

// Staff auth
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Staff invitation acceptance (public — token-gated)
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'accept'])->name('invite.accept');

// Approved CTP / E-Fundi account activation (public — token-gated)
Route::get('/activate/{token}', [ActivateController::class, 'show'])->name('activate.show');
Route::post('/activate/{token}', [ActivateController::class, 'accept'])->name('activate.accept');

// Admin (department dashboard + Module 1 review queue)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/registrations/{type}', [ReviewController::class, 'index'])->name('registrations.index');
    Route::get('/registrations/{type}/{id}', [ReviewController::class, 'show'])->whereNumber('id')->name('registrations.show');
    Route::get('/registrations/{type}/{id}/file/{doc}', [ReviewController::class, 'file'])->name('registrations.file');
    Route::post('/registrations/{type}/{id}/tier', [ReviewController::class, 'setTier'])->whereNumber('id')->name('registrations.tier');
    Route::post('/registrations/{type}/{id}/approve', [ReviewController::class, 'approve'])->name('registrations.approve');
    Route::post('/registrations/{type}/{id}/reject', [ReviewController::class, 'reject'])->name('registrations.reject');
    Route::post('/registrations/{type}/{id}/reissue', [ReviewController::class, 'reissue'])->name('registrations.reissue');

    // Site content / CMS
    Route::get('/content', [ContentController::class, 'index'])->name('content.index');
    Route::get('/content/{page}', [ContentController::class, 'edit'])->name('content.edit');
    Route::post('/content/{page}', [ContentController::class, 'update'])->name('content.update');
    Route::post('/content/{page}/sections', [ContentController::class, 'addSection'])->name('content.section.add');
    Route::delete('/content/{page}/sections/{section}', [ContentController::class, 'deleteSection'])->name('content.section.delete');
    Route::post('/content/sections/{section}/fields', [ContentController::class, 'addField'])->name('content.field.add');
    Route::delete('/content/fields/{field}', [ContentController::class, 'deleteField'])->name('content.field.delete');
    Route::post('/content/sections/{section}/entries', [ContentController::class, 'addEntry'])->name('content.entry.add');
    Route::delete('/content/entries/{entry}', [ContentController::class, 'deleteEntry'])->name('content.entry.delete');

    // Records
    Route::get('/partners', [RecordsController::class, 'partners'])->name('partners.index');
    Route::post('/partners', [RecordsController::class, 'storePartner'])->name('partners.store');
    Route::patch('/partners/{partner}', [RecordsController::class, 'updatePartner'])->name('partners.update');
    Route::post('/partners/{partner}/toggle', [RecordsController::class, 'togglePartner'])->name('partners.toggle');
    Route::delete('/partners/{partner}', [RecordsController::class, 'deletePartner'])->name('partners.delete');
    Route::get('/donations', [RecordsController::class, 'donations'])->name('donations.index');
    Route::get('/messages', [RecordsController::class, 'messages'])->name('messages.index');

    // Projects (admin-posted, never shown on public pages)
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/status', [ProjectController::class, 'setStatus'])->name('projects.status');
    Route::post('/projects/{project}/award/{application}', [ProjectController::class, 'award'])->name('projects.award');
    Route::post('/projects/{project}/decline/{application}', [ProjectController::class, 'decline'])->name('projects.decline');
    Route::post('/projects/{project}/release', [ProjectController::class, 'releaseAward'])->name('projects.release');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.delete');

    // Hirers (organisations recorded by staff)
    Route::get('/hirers', [HirerController::class, 'index'])->name('hirers.index');
    Route::post('/hirers', [HirerController::class, 'store'])->name('hirers.store');
    Route::patch('/hirers/{hirer}', [HirerController::class, 'update'])->name('hirers.update');
    Route::post('/hirers/{hirer}/toggle', [HirerController::class, 'toggle'])->name('hirers.toggle');
    Route::delete('/hirers/{hirer}', [HirerController::class, 'destroy'])->name('hirers.delete');

    // Staff & roles
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff/invite', [StaffController::class, 'invite'])->name('staff.invite');
    Route::post('/staff/invites/{invite}/revoke', [StaffController::class, 'revoke'])->name('staff.invite.revoke');
    Route::post('/staff/users/{user}', [StaffController::class, 'updateRole'])->name('staff.users.update');
});

// Member portal — approved CTPs and E-Fundis (never public, never staff)
Route::prefix('portal')->name('portal.')->middleware(['auth', 'member'])->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/projects/{project}', [PortalController::class, 'project'])->name('project');
    Route::post('/projects/{project}/apply', [PortalController::class, 'apply'])->name('apply');
    Route::post('/applications/{application}/withdraw', [PortalController::class, 'withdraw'])->name('withdraw');
});
