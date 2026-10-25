<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\BudgetController;
use App\Http\Controllers\Admin\CertificationController;
use App\Http\Controllers\Admin\CohortController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\ConstructionController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CtpProfileController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\ExecutionController;
use App\Http\Controllers\Admin\EwasteController;
use App\Http\Controllers\Admin\FundiController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\HirerController;
use App\Http\Controllers\Admin\MeGisController;
use App\Http\Controllers\Admin\NeedController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\ProgrammeController;
use App\Http\Controllers\Admin\ProcurementController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RecordsController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TechnologyController;
use App\Http\Controllers\Admin\TrackController;
use App\Http\Controllers\Admin\TrainerController;
use App\Http\Controllers\Admin\TrainingController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Auth\ActivateController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\InspectionFixController;
use App\Http\Controllers\TechnologyJobController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\WorkOrderPortalController;
use App\Support\Departments;
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
Route::prefix('register')->name('register.')->middleware('throttle:8,1')->group(function () {
    Route::get('/ctp', [RegistrationController::class, 'ctpForm'])->name('ctp');
    Route::post('/ctp', [RegistrationController::class, 'storeCtp'])->name('ctp.store');
    Route::get('/fundi', [RegistrationController::class, 'fundiForm'])->name('fundi');
    Route::post('/fundi', [RegistrationController::class, 'storeFundi'])->name('fundi.store');
    Route::get('/{type}/success/{reference}', [RegistrationController::class, 'success'])
        ->whereIn('type', ['ctp', 'fundi'])->name('success');
});

// Staff & member auth
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// The emailed code that confirms it is really the account holder — for a new account and for every office sign-in.
Route::get('/verify', [OtpVerificationController::class, 'show'])->name('otp.verify');
Route::post('/verify', [OtpVerificationController::class, 'verify'])->middleware('throttle:12,1')->name('otp.confirm');
Route::post('/verify/resend', [OtpVerificationController::class, 'resend'])->name('otp.resend');
Route::post('/verify/cancel', [OtpVerificationController::class, 'cancel'])->name('otp.cancel');

// Forgot the password: a link that lives for the minutes set in config/account.php and works once.
Route::get('/forgot-password', [PasswordResetController::class, 'show'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password/{token}', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');

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
    Route::post('/donations', [RecordsController::class, 'storeDonation'])->name('donations.store');
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
    Route::post('/projects/{project}/materials', [ProjectController::class, 'storeMaterial'])->whereNumber('project')->name('projects.materials.store');
    Route::post('/projects/materials/{material}/supply', [ProjectController::class, 'supplyMaterial'])->whereNumber('material')->name('projects.materials.supply');
    Route::patch('/projects/materials/{material}', [ProjectController::class, 'updateMaterial'])->whereNumber('material')->name('projects.materials.update');
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
    Route::post('/promoters/{ctp}/photo', [CtpProfileController::class, 'storePhoto'])->whereNumber('ctp')->name('promoters.photo');
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
    Route::get('/cohorts/results', [CohortController::class, 'results'])->name('cohorts.results');
    Route::get('/cohorts/create', [CohortController::class, 'create'])->name('cohorts.create');
    Route::post('/cohorts', [CohortController::class, 'store'])->name('cohorts.store');
    Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])->whereNumber('cohort')->name('cohorts.show');
    Route::get('/cohorts/{cohort}/edit', [CohortController::class, 'edit'])->whereNumber('cohort')->name('cohorts.edit');
    Route::patch('/cohorts/{cohort}', [CohortController::class, 'update'])->whereNumber('cohort')->name('cohorts.update');
    Route::post('/cohorts/{cohort}/status', [CohortController::class, 'setStatus'])->whereNumber('cohort')->name('cohorts.status');
    Route::post('/cohorts/{cohort}/enrol', [CohortController::class, 'enrol'])->whereNumber('cohort')->name('cohorts.enrol');

    Route::post('/enrollments/{enrollment}/decision', [CohortController::class, 'decide'])->whereNumber('enrollment')->name('enrollments.decide');
    Route::post('/enrollments/{enrollment}/fee', [CohortController::class, 'fee'])->whereNumber('enrollment')->name('enrollments.fee');
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

    // Module 5 — E-Fundi Management. The workforce record: grade, skills, counties, availability, blocks.
    Route::get('/fundis', [FundiController::class, 'index'])->name('fundis.index');
    Route::get('/fundis/create', [FundiController::class, 'create'])->name('fundis.create');
    Route::post('/fundis', [FundiController::class, 'store'])->name('fundis.store');
    Route::get('/fundis/{fundi}', [FundiController::class, 'show'])->whereNumber('fundi')->name('fundis.show');
    Route::get('/fundis/{fundi}/file/{slot}', [FundiController::class, 'file'])->whereNumber('fundi')->name('fundis.file');
    Route::post('/fundis/{fundi}/photo', [FundiController::class, 'storePhoto'])->whereNumber('fundi')->name('fundis.photo');
    Route::post('/fundis/{fundi}/tier', [FundiController::class, 'setTier'])->whereNumber('fundi')->name('fundis.tier');
    Route::post('/fundis/{fundi}/availability', [FundiController::class, 'setAvailability'])->whereNumber('fundi')->name('fundis.availability');
    Route::post('/fundis/{fundi}/skills', [FundiController::class, 'updateSkills'])->whereNumber('fundi')->name('fundis.skills');
    Route::post('/fundis/{fundi}/place', [FundiController::class, 'updatePlace'])->whereNumber('fundi')->name('fundis.place');
    Route::post('/fundis/{fundi}/fee', [FundiController::class, 'markFee'])->whereNumber('fundi')->name('fundis.fee');
    Route::post('/fundis/{fundi}/approve', [FundiController::class, 'approve'])->whereNumber('fundi')->name('fundis.approve');
    Route::post('/fundis/{fundi}/reject', [FundiController::class, 'reject'])->whereNumber('fundi')->name('fundis.reject');
    Route::post('/fundis/{fundi}/reissue', [FundiController::class, 'reissue'])->whereNumber('fundi')->name('fundis.reissue');
    Route::post('/fundis/{fundi}/block', [FundiController::class, 'block'])->whereNumber('fundi')->name('fundis.block');
    Route::post('/fundis/{fundi}/reinstate', [FundiController::class, 'reinstate'])->whereNumber('fundi')->name('fundis.reinstate');

    // Module 6 — the marketplace (§9). Posting and applications stay on the project; this desk finds the right fundi and records their answer.
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('/marketplace/{project}', [MarketplaceController::class, 'show'])->whereNumber('project')->name('marketplace.show');
    Route::post('/marketplace/{project}/run', [MarketplaceController::class, 'run'])->whereNumber('project')->name('marketplace.run');
    Route::post('/marketplace/{project}/invite', [MarketplaceController::class, 'invite'])->whereNumber('project')->name('marketplace.invite');
    Route::post('/marketplace/{project}/hand-pick', [MarketplaceController::class, 'handPick'])->whereNumber('project')->name('marketplace.handpick');
    Route::post('/marketplace/{project}/award/{match}', [MarketplaceController::class, 'award'])->whereNumber('project')->whereNumber('match')->name('marketplace.award');
    Route::post('/marketplace/{project}/auto-award', [MarketplaceController::class, 'autoAward'])->whereNumber('project')->name('marketplace.autoaward');

    // Module 7 — delivery (§10). The order an award turns into, the materials issued against it, what happened on site,
    // the hirer's inspection as the office recorded it, the dispute path, and the hand into Module 11's payout register.
    Route::get('/execution', [ExecutionController::class, 'index'])->name('execution.index');
    Route::get('/execution/{order}', [ExecutionController::class, 'show'])->whereNumber('order')->name('execution.show');
    Route::post('/execution/{order}/plan', [ExecutionController::class, 'plan'])->whereNumber('order')->name('execution.plan');
    Route::post('/execution/{order}/material/{material}/issue', [ExecutionController::class, 'issueMaterial'])
        ->whereNumber('order')->whereNumber('material')->name('execution.material.issue');
    Route::post('/execution/{order}/milestone/{milestone}/sign', [ExecutionController::class, 'signMilestone'])
        ->whereNumber('order')->whereNumber('milestone')->name('execution.milestone.sign');
    Route::post('/execution/{order}/milestone/{milestone}/unsign', [ExecutionController::class, 'unsignMilestone'])
        ->whereNumber('order')->whereNumber('milestone')->name('execution.milestone.unsign');
    Route::post('/execution/{order}/visit', [ExecutionController::class, 'visit'])->whereNumber('order')->name('execution.visit');
    Route::post('/execution/{order}/confirm', [ExecutionController::class, 'confirm'])->whereNumber('order')->name('execution.confirm');
    Route::post('/execution/{order}/dispute', [ExecutionController::class, 'dispute'])->whereNumber('order')->name('execution.dispute');
    Route::post('/execution/{order}/dispute/{dispute}/mediate', [ExecutionController::class, 'mediate'])
        ->whereNumber('order')->whereNumber('dispute')->name('execution.dispute.mediate');
    Route::post('/execution/{order}/dispute/{dispute}/decide', [ExecutionController::class, 'decide'])
        ->whereNumber('order')->whereNumber('dispute')->name('execution.dispute.decide');
    Route::post('/execution/{order}/handover', [ExecutionController::class, 'handover'])->whereNumber('order')->name('execution.handover');
    Route::post('/execution/{order}/close', [ExecutionController::class, 'close'])->whereNumber('order')->name('execution.close');
    Route::post('/execution/{order}/cancel', [ExecutionController::class, 'cancel'])->whereNumber('order')->name('execution.cancel');
    Route::get('/execution/{order}/update/{update}/photo', [ExecutionController::class, 'updatePhoto'])
        ->whereNumber('order')->whereNumber('update')->name('execution.update.photo');
    Route::get('/execution/{order}/confirmation', [ExecutionController::class, 'confirmation'])->whereNumber('order')->name('execution.confirmation');
    Route::get('/execution/{order}/dispute/{dispute}/evidence', [ExecutionController::class, 'disputeEvidence'])
        ->whereNumber('order')->whereNumber('dispute')->name('execution.dispute.evidence');

    // Module 8 — Construction & Infrastructure. The engineering file a buildable project carries (§11).
    Route::get('/construction', [ConstructionController::class, 'index'])->name('construction.index');
    Route::get('/construction/{project}', [ConstructionController::class, 'show'])->whereNumber('project')->name('construction.show');
    Route::post('/construction/{project}/assess', [ConstructionController::class, 'assess'])->whereNumber('project')->name('construction.assess');
    Route::post('/construction/{project}/boq', [ConstructionController::class, 'boq'])->whereNumber('project')->name('construction.boq');
    Route::post('/construction/boq/{line}/withdraw', [ConstructionController::class, 'withdrawLine'])->whereNumber('line')->name('construction.withdraw');
    Route::post('/construction/{project}/labour', [ConstructionController::class, 'labour'])->whereNumber('project')->name('construction.labour');
    Route::post('/construction/{project}/inspection', [ConstructionController::class, 'schedule'])->whereNumber('project')->name('construction.schedule');
    Route::post('/construction/inspection/{inspection}/report', [ConstructionController::class, 'report'])->whereNumber('inspection')->name('construction.report');
    Route::post('/construction/inspection/{inspection}/sign-off', [ConstructionController::class, 'signOff'])->whereNumber('inspection')->name('construction.signOff');
    Route::post('/construction/item/{item}/recheck', [ConstructionController::class, 'recheck'])->whereNumber('item')->name('construction.recheck');
    Route::post('/construction/{project}/variation', [ConstructionController::class, 'variation'])->whereNumber('project')->name('construction.variation');
    Route::post('/construction/variation/{variation}/decide', [ConstructionController::class, 'decide'])->whereNumber('variation')->name('construction.decide');
    Route::get('/construction/assessment/{assessment}/photo', [ConstructionController::class, 'assessmentFile'])->whereNumber('assessment')->name('construction.assessment.photo');
    Route::get('/construction/inspection/{inspection}/photo', [ConstructionController::class, 'inspectionPhoto'])->whereNumber('inspection')->name('construction.inspection.photo');
    Route::get('/construction/item/{item}/proof', [ConstructionController::class, 'itemProof'])->whereNumber('item')->name('construction.item.proof');

    // Module 9 — Technology Services. The asset register, the project register and the repair tickets (§12).
    Route::get('/technology', [TechnologyController::class, 'index'])->name('technology.index');
    Route::get('/technology/assets/create', [TechnologyController::class, 'createAsset'])->name('technology.asset.create');
    Route::post('/technology/assets', [TechnologyController::class, 'storeAsset'])->name('technology.asset.store');
    Route::get('/technology/assets/{asset}', [TechnologyController::class, 'showAsset'])->whereNumber('asset')->name('technology.asset');
    Route::post('/technology/assets/{asset}', [TechnologyController::class, 'updateAsset'])->whereNumber('asset')->name('technology.asset.update');
    Route::post('/technology/assets/{asset}/decommission', [TechnologyController::class, 'decommission'])->whereNumber('asset')->name('technology.asset.decommission');
    Route::post('/technology/assets/{asset}/ticket', [TechnologyController::class, 'openTicket'])->whereNumber('asset')->name('technology.asset.ticket');
    Route::post('/technology/tickets/{ticket}/assign', [TechnologyController::class, 'assignTicket'])->whereNumber('ticket')->name('technology.ticket.assign');
    Route::post('/technology/tickets/{ticket}/resolve', [TechnologyController::class, 'resolveTicket'])->whereNumber('ticket')->name('technology.ticket.resolve');
    Route::post('/technology/tickets/{ticket}/close', [TechnologyController::class, 'closeTicket'])->whereNumber('ticket')->name('technology.ticket.close');
    Route::get('/technology/projects/create', [TechnologyController::class, 'createProject'])->name('technology.project.create');
    Route::post('/technology/projects', [TechnologyController::class, 'storeProject'])->name('technology.project.store');
    Route::get('/technology/projects/{project}', [TechnologyController::class, 'showProject'])->whereNumber('project')->name('technology.project');
    Route::post('/technology/projects/{project}', [TechnologyController::class, 'updateProject'])->whereNumber('project')->name('technology.project.update');

    // Module 10 — E-Waste Management. The field registers, an E-Fundi forwards, the office weighs it in.
    Route::get('/ewaste', [EwasteController::class, 'index'])->name('ewaste.index');
    Route::post('/ewaste/{collection}/receive', [EwasteController::class, 'receive'])->whereNumber('collection')->name('ewaste.receive');
    Route::get('/ewaste/{collection}/photo', [EwasteController::class, 'photo'])->whereNumber('collection')->name('ewaste.photo');

    // Module 11 — Finance, Procurement & Inventory. The plan, the ledger, the purchase path, the store and the two payout streams.
    Route::get('/finance/budget', [BudgetController::class, 'index'])->name('budget.index');
    Route::post('/finance/budget', [BudgetController::class, 'save'])->name('budget.save');

    Route::get('/finance/ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::post('/finance/ledger', [LedgerController::class, 'store'])->name('ledger.store');

    Route::get('/finance/procurement', [ProcurementController::class, 'index'])->name('procurement.index');
    Route::post('/finance/procurement', [ProcurementController::class, 'store'])->name('procurement.store');
    Route::get('/finance/procurement/{purchaseRequest}', [ProcurementController::class, 'show'])->whereNumber('purchaseRequest')->name('procurement.show');
    Route::post('/finance/procurement/{purchaseRequest}/decide', [ProcurementController::class, 'decide'])->whereNumber('purchaseRequest')->name('procurement.decide');
    Route::post('/finance/procurement/{purchaseRequest}/quote', [ProcurementController::class, 'quote'])->whereNumber('purchaseRequest')->name('procurement.quote');
    Route::post('/finance/procurement/{purchaseRequest}/order', [ProcurementController::class, 'raiseOrder'])->whereNumber('purchaseRequest')->name('procurement.order');
    Route::delete('/finance/quotation/{quotation}', [ProcurementController::class, 'dropQuotation'])->whereNumber('quotation')->name('procurement.quotation.delete');
    Route::post('/finance/quotation/{quotation}/select', [ProcurementController::class, 'selectQuotation'])->whereNumber('quotation')->name('procurement.quotation.select');
    Route::post('/finance/order/{order}/issue', [ProcurementController::class, 'issueOrder'])->whereNumber('order')->name('procurement.order.issue');
    Route::post('/finance/order/{order}/receive', [ProcurementController::class, 'receiveOrder'])->whereNumber('order')->name('procurement.order.receive');
    Route::post('/finance/order/{order}/match', [ProcurementController::class, 'matchOrder'])->whereNumber('order')->name('procurement.order.match');
    Route::post('/finance/order/{order}/pay', [ProcurementController::class, 'payOrder'])->whereNumber('order')->name('procurement.order.pay');

    Route::get('/finance/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/finance/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('/finance/suppliers/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('suppliers.update');
    Route::post('/finance/suppliers/{supplier}/status', [SupplierController::class, 'setStatus'])->whereNumber('supplier')->name('suppliers.status');

    Route::get('/finance/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/finance/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/finance/inventory/{item}', [InventoryController::class, 'show'])->whereNumber('item')->name('inventory.show');
    Route::post('/finance/inventory/{item}/move', [InventoryController::class, 'move'])->whereNumber('item')->name('inventory.move');
    Route::post('/finance/inventory/{item}/reorder', [InventoryController::class, 'reorder'])->whereNumber('item')->name('inventory.reorder');

    Route::get('/finance/payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::post('/finance/payouts', [PayoutController::class, 'store'])->name('payouts.store');
    Route::get('/finance/payouts/{payout}', [PayoutController::class, 'show'])->whereNumber('payout')->name('payouts.show');
    Route::post('/finance/payouts/{payout}/hirer-payment', [PayoutController::class, 'confirmHirerPayment'])->whereNumber('payout')->name('payouts.hirer');
    Route::post('/finance/payouts/{payout}/report', [PayoutController::class, 'confirmReport'])->whereNumber('payout')->name('payouts.report');
    Route::post('/finance/payouts/{payout}/approve', [PayoutController::class, 'approve'])->whereNumber('payout')->name('payouts.approve');
    Route::post('/finance/payouts/{payout}/release', [PayoutController::class, 'release'])->whereNumber('payout')->name('payouts.release');
    Route::post('/finance/payouts/{payout}/hold', [PayoutController::class, 'hold'])->whereNumber('payout')->name('payouts.hold');
    Route::post('/finance/payouts/{payout}/resume', [PayoutController::class, 'resume'])->whereNumber('payout')->name('payouts.resume');
    Route::post('/finance/payouts/{payout}/reject', [PayoutController::class, 'reject'])->whereNumber('payout')->name('payouts.reject');

    // Module 12 — M&E, GIS and reports (§15). Read-only views of the other modules' registers, plus the trail and the programme switches.
    Route::get('/impacts', [MeGisController::class, 'index'])->name('impacts.index');
    Route::get('/gis', [MeGisController::class, 'map'])->name('gis.map');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/export', [ReportController::class, 'csv'])->name('reports.export');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/activity/export', [ActivityController::class, 'csv'])->name('activity.export');
    Route::get('/programme', [ProgrammeController::class, 'index'])->name('programme.index');
    Route::post('/programme', [ProgrammeController::class, 'update'])->name('settings.update');

    // Shared records — a department's own copy of another module's screen, so opening a record your department links
    // to never moves you out of it. Same controller, same data. Read-only by design: only the screens listed in
    // Departments::HOSTED are copied here, and no write route is registered under /in/, so changing a record stays
    // with the module that owns it. The department comes last in every URI because the owning controller receives
    // its own route parameters positionally and does not declare the department it is being shown in.
    Route::name('host.')->where(['department' => implode('|', Departments::hosts())])->group(function () {
        Route::get('/projects/in/{department}', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}/in/{department}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
        Route::get('/promoters/{ctp}/in/{department}', [CtpProfileController::class, 'show'])->whereNumber('ctp')->name('promoters.show');
        Route::get('/communities/{community}/in/{department}', [CommunityController::class, 'show'])->whereNumber('community')->name('communities.show');
        Route::get('/fundis/{fundi}/in/{department}', [FundiController::class, 'show'])->whereNumber('fundi')->name('fundis.show');
        Route::get('/cohorts/{cohort}/in/{department}', [CohortController::class, 'show'])->whereNumber('cohort')->name('cohorts.show');
        Route::get('/execution/{order}/in/{department}', [ExecutionController::class, 'show'])->whereNumber('order')->name('execution.show');
        Route::get('/finance/inventory/{item}/in/{department}', [InventoryController::class, 'show'])->whereNumber('item')->name('inventory.show');
        Route::get('/finance/payouts/{payout}/in/{department}', [PayoutController::class, 'show'])->whereNumber('payout')->name('payouts.show');
    });
});

// Member portal — the member's own interface. Never staff, never public.
Route::prefix('portal')->name('portal.')->middleware(['auth', 'member'])->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/training', [PortalController::class, 'training'])->name('training');
    Route::post('/training/apply', [PortalController::class, 'applyCohort'])->name('training.apply');
    Route::post('/training/{enrollment}/withdraw', [PortalController::class, 'withdrawSeat'])->whereNumber('enrollment')->name('training.withdraw');
    Route::get('/trainer', [PortalController::class, 'trainer'])->name('trainer');
    // Module 5 — the E-Fundi's own record. They keep availability, skills and counties; the office keeps the grade.
    Route::get('/efundi', [PortalController::class, 'efundi'])->name('efundi');
    Route::post('/efundi/availability', [PortalController::class, 'saveAvailability'])->name('efundi.availability');
    Route::post('/efundi/skills', [PortalController::class, 'saveSkills'])->name('efundi.skills');
    Route::get('/efundi/file/{slot}', [PortalController::class, 'efundiFile'])->name('efundi.file');
    // The face on their own profile — a CTP and an E-Fundi are served the same way.
    Route::get('/photo', [PortalController::class, 'photo'])->name('photo');
    Route::post('/photo', [PortalController::class, 'storePhoto'])->name('photo.store');
    Route::get('/trainer/{enrollment}', [PortalController::class, 'trainee'])->whereNumber('enrollment')->name('trainer.trainee');
    Route::post('/trainer', [PortalController::class, 'applyTrainer'])->name('trainer.apply');
    Route::post('/trainer/progress', [PortalController::class, 'markProgress'])->name('trainer.progress');
    Route::post('/trainer/{enrollment}/modules/{module}', [PortalController::class, 'markModule'])->whereNumber('enrollment')->whereNumber('module')->name('trainer.module.mark');
    Route::delete('/trainer/{enrollment}/modules/{module}', [PortalController::class, 'unmarkModule'])->whereNumber('enrollment')->whereNumber('module')->name('trainer.module.unmark');
    Route::post('/trainer/{enrollment}/documents', [PortalController::class, 'storeDocument'])->whereNumber('enrollment')->name('trainer.document.store');
    Route::get('/trainer/{enrollment}/documents/{document}', [PortalController::class, 'document'])->whereNumber('enrollment')->whereNumber('document')->name('trainer.document.show');
    // Module 10 — both members register e-waste in the field; an E-Fundi also forwards what a CTP logged.
    Route::get('/ewaste', [PortalController::class, 'ewaste'])->name('ewaste');
    Route::post('/ewaste', [PortalController::class, 'storeEwaste'])->name('ewaste.store');
    Route::post('/ewaste/{collection}/forward', [PortalController::class, 'forwardEwaste'])->whereNumber('collection')->name('ewaste.forward');
    Route::get('/ewaste/{collection}/photo', [PortalController::class, 'ewastePhoto'])->whereNumber('collection')->name('ewaste.photo');
    // Module 11 — a member raises a purchase request (§14.4 step 1) and an E-Fundi reads their two income streams (§14.6).
    Route::get('/procurement', [PortalController::class, 'procurement'])->name('procurement');
    Route::post('/procurement', [PortalController::class, 'storeProcurement'])->name('procurement.store');
    Route::get('/earnings', [PortalController::class, 'earnings'])->name('earnings');
    Route::get('/mobilisation', [PortalController::class, 'mobilisation'])->name('mobilisation');
    Route::get('/communities', [PortalController::class, 'communities'])->name('communities');
    Route::post('/communities', [PortalController::class, 'storeCommunity'])->name('community.store');
    Route::post('/communities/need', [PortalController::class, 'storeNeed'])->name('need.store');
    Route::post('/communities/beneficiary', [PortalController::class, 'storeBeneficiary'])->name('beneficiary.store');
    Route::get('/projects', [PortalController::class, 'projects'])->name('projects');
    Route::get('/applications', [PortalController::class, 'applications'])->name('applications');
    // Module 6 — the matching desk puts a project to a fundi, and they answer here (§9.4 step 4).
    Route::get('/invitations', [PortalController::class, 'invitations'])->name('invitations');
    Route::post('/invitations/{match}/respond', [PortalController::class, 'respondInvite'])->whereNumber('match')->name('invite.respond');
    Route::get('/projects/{project}', [PortalController::class, 'project'])->name('project');
    Route::post('/projects/{project}/apply', [PortalController::class, 'apply'])->name('apply');
    Route::post('/applications/{application}/withdraw', [PortalController::class, 'withdraw'])->name('withdraw');

    // Module 7 — the worker's own side of the order (§10.4 steps 1, 3, 5 and §10.6 step 2).
    Route::get('/work', [WorkOrderPortalController::class, 'index'])->name('work');
    Route::get('/work/{order}', [WorkOrderPortalController::class, 'show'])->whereNumber('order')->name('work.show');
    Route::post('/work/{order}/accept', [WorkOrderPortalController::class, 'accept'])->whereNumber('order')->name('work.accept');
    Route::post('/work/{order}/log', [WorkOrderPortalController::class, 'log'])->whereNumber('order')->name('work.log');
    Route::post('/work/{order}/complete', [WorkOrderPortalController::class, 'complete'])->whereNumber('order')->name('work.complete');
    Route::post('/work/{order}/dispute/{dispute}/answer', [WorkOrderPortalController::class, 'answer'])
        ->whereNumber('order')->whereNumber('dispute')->name('work.dispute.answer');
    Route::get('/work/{order}/update/{update}/photo', [WorkOrderPortalController::class, 'photo'])
        ->whereNumber('order')->whereNumber('update')->name('work.update.photo');
    Route::get('/work/{order}/dispute/{dispute}/evidence', [WorkOrderPortalController::class, 'evidence'])
        ->whereNumber('order')->whereNumber('dispute')->name('work.dispute.evidence');

    // Module 8 — the worker answers an inspection item the inspector failed, with the photograph of the rework (§11.4 step 5).
    Route::get('/fixes', [InspectionFixController::class, 'index'])->name('fixes');
    Route::post('/fixes/{item}/proof', [InspectionFixController::class, 'prove'])->whereNumber('item')->name('fixes.prove');
    Route::get('/fixes/{item}/photo', [InspectionFixController::class, 'proof'])->whereNumber('item')->name('fixes.proof');

    // Module 9 — the fundi's own repair jobs. A ticket assigned to them is answered here; the office confirms and closes it (§12.7).
    Route::get('/tech', [TechnologyJobController::class, 'index'])->name('tech.jobs');
    Route::post('/tech/{ticket}/resolve', [TechnologyJobController::class, 'resolve'])->whereNumber('ticket')->name('tech.resolve');
});
