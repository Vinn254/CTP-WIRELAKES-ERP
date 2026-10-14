<?php

use App\Http\Controllers\Admin\CertificationController;
use App\Http\Controllers\Admin\CohortController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CtpProfileController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HirerController;
use App\Http\Controllers\Admin\NeedController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RecordsController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\TrackController;
use App\Http\Controllers\Admin\TrainerController;
use App\Http\Controllers\Admin\TrainingController;
use App\Http\Controllers\Admin\VenueController;
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

// Admin — departments, then the screens inside each one
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/', [DepartmentController::class, 'index'])->name('departments');
    Route::get('/department/{department}', [DepartmentController::class, 'show'])->name('department');
    Route::get('/registrations/{type}', [ReviewController::class, 'index'])->name('registrations.index');
    Route::get('/registrations/{type}/{id}', [ReviewController::class, 'show'])->whereNumber('id')->name('registrations.show');
    Route::get('/registrations/{type}/{id}/file/{doc}', [ReviewController::class, 'file'])->name('registrations.file');
    Route::post('/registrations/{type}/{id}/tier', [ReviewController::class, 'setTier'])->whereNumber('id')->name('registrations.tier');
    Route::post('/registrations/{type}/{id}/approve', [ReviewController::class, 'approve'])->name('registrations.approve');
    Route::post('/registrations/{type}/{id}/reject', [ReviewController::class, 'reject'])->name('registrations.reject');
    Route::post('/registrations/{type}/{id}/reissue', [ReviewController::class, 'reissue'])->name('registrations.reissue');
    Route::post('/registrations/{type}/{id}/fee', [ReviewController::class, 'markFee'])->name('registrations.fee');

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
    Route::post('/projects/{project}/assign', [ProjectController::class, 'assignMember'])->name('projects.assign');
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

    // Module 2 — CTP Promoter Management. Everything a CTP needs is decided here, not in Module 1.
    Route::get('/promoters', [CtpProfileController::class, 'index'])->name('promoters.index');
    Route::get('/promoters/create', [CtpProfileController::class, 'create'])->name('promoters.create');
    Route::post('/promoters', [CtpProfileController::class, 'store'])->name('promoters.store');
    Route::get('/promoters/{ctp}', [CtpProfileController::class, 'show'])->whereNumber('ctp')->name('promoters.show');
    Route::get('/promoters/{ctp}/file/{slot}', [CtpProfileController::class, 'file'])->whereNumber('ctp')->name('promoters.file');
    Route::get('/promoters/{ctp}/edit', [CtpProfileController::class, 'edit'])->whereNumber('ctp')->name('promoters.edit');
    Route::patch('/promoters/{ctp}', [CtpProfileController::class, 'update'])->whereNumber('ctp')->name('promoters.update');
    Route::post('/promoters/{ctp}/status', [CtpProfileController::class, 'setStatus'])->whereNumber('ctp')->name('promoters.status');
    Route::post('/promoters/{ctp}/referrer', [CtpProfileController::class, 'setReferrer'])->whereNumber('ctp')->name('promoters.referrer');
    Route::post('/promoters/{ctp}/fee', [CtpProfileController::class, 'markFee'])->whereNumber('ctp')->name('promoters.fee');
    Route::post('/promoters/{ctp}/sponsor', [CtpProfileController::class, 'setSponsor'])->whereNumber('ctp')->name('promoters.sponsor');
    Route::post('/promoters/{ctp}/approve', [CtpProfileController::class, 'approve'])->whereNumber('ctp')->name('promoters.approve');
    Route::post('/promoters/{ctp}/reject', [CtpProfileController::class, 'reject'])->whereNumber('ctp')->name('promoters.reject');
    Route::post('/promoters/{ctp}/reissue', [CtpProfileController::class, 'reissue'])->whereNumber('ctp')->name('promoters.reissue');
    Route::post('/promoters/{ctp}/tier', [CtpProfileController::class, 'setTier'])->whereNumber('ctp')->name('promoters.tier');

    // Module 3 — Community Management. Communities, their needs pipeline and their beneficiaries.
    Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
    Route::get('/communities/create', [CommunityController::class, 'create'])->name('communities.create');
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::get('/communities/{community}', [CommunityController::class, 'show'])->whereNumber('community')->name('communities.show');
    Route::get('/communities/{community}/edit', [CommunityController::class, 'edit'])->whereNumber('community')->name('communities.edit');
    Route::patch('/communities/{community}', [CommunityController::class, 'update'])->whereNumber('community')->name('communities.update');
    Route::post('/communities/{community}/approve', [CommunityController::class, 'approve'])->whereNumber('community')->name('communities.approve');
    Route::post('/communities/{community}/reject', [CommunityController::class, 'reject'])->whereNumber('community')->name('communities.reject');
    Route::post('/communities/{community}/ctp', [CommunityController::class, 'assignCtp'])->whereNumber('community')->name('communities.ctp');
    Route::post('/communities/{community}/ctp/{ctp}/remove', [CommunityController::class, 'removeCtp'])->whereNumber('community')->name('communities.ctp.remove');
    Route::post('/communities/{community}/beneficiary', [CommunityController::class, 'storeBeneficiary'])->whereNumber('community')->name('communities.beneficiary');

    // Needs assessment — the queue across every community
    Route::get('/needs', [NeedController::class, 'index'])->name('needs.index');
    Route::get('/needs/{need}/photo', [NeedController::class, 'photo'])->whereNumber('need')->name('needs.photo');
    Route::post('/needs/{need}/priority', [NeedController::class, 'setPriority'])->whereNumber('need')->name('needs.priority');
    Route::post('/needs/{need}/status', [NeedController::class, 'setStatus'])->whereNumber('need')->name('needs.status');
    Route::post('/needs/{need}/convert', [NeedController::class, 'convert'])->whereNumber('need')->name('needs.convert');

    // Beneficiaries — the people a project reached
    Route::get('/beneficiaries', [CommunityController::class, 'beneficiaries'])->name('beneficiaries.index');
    Route::delete('/beneficiaries/{beneficiary}', [CommunityController::class, 'destroyBeneficiary'])->name('beneficiaries.delete');

    // Module 4 — the trainings the programme itself runs. Everything else on this module picks from this list.
    Route::get('/trainings', [TrainingController::class, 'index'])->name('trainings.index');
    Route::post('/trainings', [TrainingController::class, 'store'])->name('trainings.store');
    Route::patch('/trainings/{training}', [TrainingController::class, 'update'])->whereNumber('training')->name('trainings.update');
    Route::post('/trainings/{training}/toggle', [TrainingController::class, 'toggle'])->whereNumber('training')->name('trainings.toggle');
    Route::delete('/trainings/{training}', [TrainingController::class, 'destroy'])->whereNumber('training')->name('trainings.delete');

    // The modules the office puts under a training — what the trainer delivers and the trainee must get through.
    Route::post('/trainings/{training}/modules', [TrainingController::class, 'storeModule'])->whereNumber('training')->name('trainings.modules.store');
    Route::patch('/trainings/{training}/modules/{module}', [TrainingController::class, 'updateModule'])->whereNumber('training')->whereNumber('module')->name('trainings.modules.update');
    Route::delete('/trainings/{training}/modules/{module}', [TrainingController::class, 'destroyModule'])->whereNumber('training')->whereNumber('module')->name('trainings.modules.delete');

    // Module 4 — Training & Certification. Where training happens, the cohorts CTPs apply for and the certificate queue.
    Route::get('/venues', [VenueController::class, 'index'])->name('venues.index');
    Route::post('/venues', [VenueController::class, 'store'])->name('venues.store');
    Route::patch('/venues/{venue}', [VenueController::class, 'update'])->whereNumber('venue')->name('venues.update');
    Route::post('/venues/{venue}/toggle', [VenueController::class, 'toggle'])->whereNumber('venue')->name('venues.toggle');
    Route::delete('/venues/{venue}', [VenueController::class, 'destroy'])->whereNumber('venue')->name('venues.delete');

    Route::get('/trainers', [TrainerController::class, 'index'])->name('trainers.index');
    Route::post('/trainers', [TrainerController::class, 'store'])->name('trainers.store');
    Route::patch('/trainers/{trainer}', [TrainerController::class, 'update'])->whereNumber('trainer')->name('trainers.update');
    Route::post('/trainers/{trainer}/decision', [TrainerController::class, 'decide'])->whereNumber('trainer')->name('trainers.decide');

    Route::get('/cohorts', [CohortController::class, 'index'])->name('cohorts.index');
    Route::get('/cohorts/create', [CohortController::class, 'create'])->name('cohorts.create');
    Route::post('/cohorts', [CohortController::class, 'store'])->name('cohorts.store');
    Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])->whereNumber('cohort')->name('cohorts.show');
    Route::get('/cohorts/{cohort}/edit', [CohortController::class, 'edit'])->whereNumber('cohort')->name('cohorts.edit');
    Route::patch('/cohorts/{cohort}', [CohortController::class, 'update'])->whereNumber('cohort')->name('cohorts.update');
    Route::post('/cohorts/{cohort}/status', [CohortController::class, 'setStatus'])->whereNumber('cohort')->name('cohorts.status');
    Route::post('/cohorts/{cohort}/enrol', [CohortController::class, 'enrol'])->whereNumber('cohort')->name('cohorts.enrol');

    Route::post('/enrollments/{enrollment}/decision', [CohortController::class, 'decide'])->whereNumber('enrollment')->name('enrollments.decide');
    Route::post('/enrollments/{enrollment}/stage', [CohortController::class, 'setStage'])->whereNumber('enrollment')->name('enrollments.stage');
    Route::delete('/enrollments/{enrollment}', [CohortController::class, 'drop'])->whereNumber('enrollment')->name('enrollments.delete');

    // One module marked on one seat, and the paperwork a partner school or a trainer sends in.
    Route::post('/enrollments/{enrollment}/modules/{module}', [CohortController::class, 'markModule'])->whereNumber('enrollment')->whereNumber('module')->name('enrollments.module.mark');
    Route::delete('/enrollments/{enrollment}/modules/{module}', [CohortController::class, 'unmarkModule'])->whereNumber('enrollment')->whereNumber('module')->name('enrollments.module.unmark');
    Route::post('/enrollments/{enrollment}/documents', [CohortController::class, 'storeDocument'])->whereNumber('enrollment')->name('enrollments.document.store');
    Route::get('/enrollments/{enrollment}/documents/{document}', [CohortController::class, 'document'])->whereNumber('enrollment')->whereNumber('document')->name('enrollments.document.show');

    // Module 4 — the track page. How each CTP is actually getting on, and what is left before certification.
    Route::get('/track', [TrackController::class, 'index'])->name('track.index');
    Route::get('/track/{enrollment}', [TrackController::class, 'show'])->whereNumber('enrollment')->name('track.show');
    Route::post('/track/{enrollment}/progress', [TrackController::class, 'storeProgress'])->whereNumber('enrollment')->name('track.progress');

    Route::get('/certification', [CertificationController::class, 'index'])->name('certification.index');
    Route::post('/certification/{enrollment}/recommend', [CertificationController::class, 'recommend'])->whereNumber('enrollment')->name('certification.recommend');
    Route::post('/certification/{certification}/approve', [CertificationController::class, 'approve'])->whereNumber('certification')->name('certification.approve');
    Route::post('/certification/{certification}/decline', [CertificationController::class, 'decline'])->whereNumber('certification')->name('certification.decline');
    Route::post('/certification/{certification}/revoke', [CertificationController::class, 'revoke'])->whereNumber('certification')->name('certification.revoke');
});

// Member portal — the member's own interface. Never staff, never public.
Route::prefix('portal')->name('portal.')->middleware(['auth', 'member'])->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/training', [PortalController::class, 'training'])->name('training');
    Route::post('/training/apply', [PortalController::class, 'applyCohort'])->name('training.apply');
    Route::post('/training/{enrollment}/withdraw', [PortalController::class, 'withdrawSeat'])->whereNumber('enrollment')->name('training.withdraw');
    Route::get('/trainer', [PortalController::class, 'trainer'])->name('trainer');
    Route::post('/trainer', [PortalController::class, 'applyTrainer'])->name('trainer.apply');
    Route::post('/trainer/progress', [PortalController::class, 'markProgress'])->name('trainer.progress');
    Route::post('/trainer/{enrollment}/modules/{module}', [PortalController::class, 'markModule'])->whereNumber('enrollment')->whereNumber('module')->name('trainer.module.mark');
    Route::delete('/trainer/{enrollment}/modules/{module}', [PortalController::class, 'unmarkModule'])->whereNumber('enrollment')->whereNumber('module')->name('trainer.module.unmark');
    Route::post('/trainer/{enrollment}/documents', [PortalController::class, 'storeDocument'])->whereNumber('enrollment')->name('trainer.document.store');
    Route::get('/trainer/{enrollment}/documents/{document}', [PortalController::class, 'document'])->whereNumber('enrollment')->whereNumber('document')->name('trainer.document.show');
    Route::get('/mobilisation', [PortalController::class, 'mobilisation'])->name('mobilisation');
    Route::get('/communities', [PortalController::class, 'communities'])->name('communities');
    Route::post('/communities', [PortalController::class, 'storeCommunity'])->name('community.store');
    Route::post('/communities/need', [PortalController::class, 'storeNeed'])->name('need.store');
    Route::post('/communities/beneficiary', [PortalController::class, 'storeBeneficiary'])->name('beneficiary.store');
    Route::get('/projects', [PortalController::class, 'projects'])->name('projects');
    Route::get('/applications', [PortalController::class, 'applications'])->name('applications');
    Route::get('/projects/{project}', [PortalController::class, 'project'])->name('project');
    Route::post('/projects/{project}/apply', [PortalController::class, 'apply'])->name('apply');
    Route::post('/applications/{application}/withdraw', [PortalController::class, 'withdraw'])->name('withdraw');
});
