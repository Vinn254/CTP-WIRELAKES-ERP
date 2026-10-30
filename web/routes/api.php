<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MemberAccountApiController;
use App\Http\Controllers\Api\MemberApiController;
use App\Http\Controllers\Api\MemberClassApiController;
use App\Http\Controllers\Api\MemberFieldApiController;
use App\Http\Controllers\Api\MemberPortalApiController;
use App\Http\Controllers\Api\MemberWorkApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WireLakes Technologies API routes
|--------------------------------------------------------------------------
| Consumed by both the Blade web app and the Flutter mobile app.
| Auth is via Laravel Sanctum personal access tokens.
*/

// Health check — used by ops, load balancers and the Flutter splash screen
Route::get('/health', [HealthController::class, 'index'])->name('api.health');

// Public auth endpoints — every one of them is throttled: the login form, the code and the reset link are the
// three doors a brute-force script would knock on.
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('api.auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:12,1')->name('api.auth.otp.verify');
Route::post('/auth/otp/resend', [AuthController::class, 'resendOtp'])->middleware('throttle:6,1')->name('api.auth.otp.resend');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('api.auth.forgot');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('api.auth.reset');

// Protected endpoints (require a valid Sanctum token, and sign out when the phone is left idle)
Route::middleware(['auth:sanctum', 'idle'])->group(function () {
    Route::get('/me',       [AuthController::class, 'me'])->name('api.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
});

// The member portal over the API — the same member-resolved screens the web portal serves.
// Never staff, never public; the member is read from the token, never a url.
Route::middleware(['auth:sanctum', 'idle', 'member'])->group(function () {
    Route::get('/me/board', [MemberApiController::class, 'board'])->name('api.me.board');
    Route::get('/me/photo', [MemberApiController::class, 'photo'])->name('api.me.photo');
    Route::get('/projects', [MemberApiController::class, 'projects'])->name('api.projects');
    Route::post('/projects/{project}/apply', [MemberApiController::class, 'apply'])->whereNumber('project')->name('api.projects.apply');
    Route::get('/projects/{project}', [MemberApiController::class, 'show'])->whereNumber('project')->name('api.projects.show');
    Route::get('/applications', [MemberApiController::class, 'applications'])->name('api.applications');
    Route::post('/applications/{application}/withdraw', [MemberApiController::class, 'withdraw'])->whereNumber('application')->name('api.applications.withdraw');

    // The trainee's own class and reading, the office's bill against a name, and a worker's leave.
    // Each one answers with the same query and the same guards as the portal page it stands for.
    Route::get('/me/class', [MemberClassApiController::class, 'classes'])->name('api.me.class');
    Route::get('/me/class/{cohort}', [MemberClassApiController::class, 'show'])->whereNumber('cohort')->name('api.me.class.show');
    Route::get('/me/class/{cohort}/material/{material}', [MemberClassApiController::class, 'materialFile'])->whereNumber('cohort')->whereNumber('material')->name('api.me.class.material');
    Route::get('/me/library', [MemberClassApiController::class, 'library'])->name('api.me.library');
    Route::get('/me/library/{item}', [MemberClassApiController::class, 'libraryFile'])->whereNumber('item')->name('api.me.library.open');

    Route::get('/me/invoices', [MemberAccountApiController::class, 'invoices'])->name('api.me.invoices');
    Route::get('/me/leave', [MemberAccountApiController::class, 'leave'])->name('api.me.leave');
    Route::post('/me/leave', [MemberAccountApiController::class, 'applyLeave'])->name('api.me.leave.apply');
    Route::post('/me/leave/{leave}/cancel', [MemberAccountApiController::class, 'cancelLeave'])->whereNumber('leave')->name('api.me.leave.cancel');
});

// More member screens, each the exact portal page: the kind guard (fundi/CTP) lives in the controller,
// so a CTP hitting an E-Fundi route and vice-versa is refused just as the web sidebar never offers it.
Route::middleware(['auth:sanctum', 'idle', 'member'])->group(function () {
    Route::get('/efundi', [MemberPortalApiController::class, 'efundi'])->name('api.efundi');
    Route::post('/efundi/availability', [MemberPortalApiController::class, 'saveAvailability'])->name('api.efundi.availability');
    Route::post('/efundi/skills', [MemberPortalApiController::class, 'saveSkills'])->name('api.efundi.skills');
    Route::get('/efundi/file/{slot}', [MemberPortalApiController::class, 'efundiFile'])->where('slot', '[A-Za-z0-9_\-]+')->name('api.efundi.file');

    Route::get('/invitations', [MemberPortalApiController::class, 'invitations'])->name('api.invitations');
    Route::post('/invitations/{match}/respond', [MemberPortalApiController::class, 'respondInvite'])->whereNumber('match')->name('api.invitations.respond');

    Route::get('/training', [MemberPortalApiController::class, 'training'])->name('api.training');
    Route::post('/training/apply', [MemberPortalApiController::class, 'applyCohort'])->name('api.training.apply');
    Route::post('/training/{enrollment}/withdraw', [MemberPortalApiController::class, 'withdrawSeat'])->whereNumber('enrollment')->name('api.training.withdraw');
});

// The field screens — communities, mobilisation, e-waste, purchase requests, earnings and the trainer's roll.
Route::middleware(['auth:sanctum', 'idle', 'member'])->group(function () {
    Route::get('/communities', [MemberFieldApiController::class, 'communities'])->name('api.communities');
    Route::post('/communities', [MemberFieldApiController::class, 'storeCommunity'])->name('api.communities.store');
    Route::post('/communities/need', [MemberFieldApiController::class, 'storeNeed'])->name('api.communities.need');
    Route::post('/communities/beneficiary', [MemberFieldApiController::class, 'storeBeneficiary'])->name('api.communities.beneficiary');
    Route::get('/mobilisation', [MemberFieldApiController::class, 'mobilisation'])->name('api.mobilisation');

    Route::get('/ewaste', [MemberFieldApiController::class, 'ewaste'])->name('api.ewaste');
    Route::post('/ewaste', [MemberFieldApiController::class, 'storeEwaste'])->name('api.ewaste.store');
    Route::post('/ewaste/{collection}/forward', [MemberFieldApiController::class, 'forwardEwaste'])->whereNumber('collection')->name('api.ewaste.forward');
    Route::get('/ewaste/{collection}/photo', [MemberFieldApiController::class, 'ewastePhoto'])->whereNumber('collection')->name('api.ewaste.photo');

    Route::get('/procurement', [MemberFieldApiController::class, 'procurement'])->name('api.procurement');
    Route::post('/procurement', [MemberFieldApiController::class, 'storeProcurement'])->name('api.procurement.store');
    Route::get('/earnings', [MemberFieldApiController::class, 'earnings'])->name('api.earnings');

    Route::get('/trainer', [MemberFieldApiController::class, 'trainer'])->name('api.trainer');
    Route::post('/trainer/apply', [MemberFieldApiController::class, 'applyTrainer'])->name('api.trainer.apply');
    Route::post('/trainer/progress', [MemberFieldApiController::class, 'markProgress'])->name('api.trainer.progress');
    Route::post('/trainer/{enrollment}/modules/{module}', [MemberFieldApiController::class, 'markModule'])->whereNumber('enrollment')->whereNumber('module')->name('api.trainer.module.mark');
    Route::delete('/trainer/{enrollment}/modules/{module}', [MemberFieldApiController::class, 'unmarkModule'])->whereNumber('enrollment')->whereNumber('module')->name('api.trainer.module.unmark');
    Route::post('/trainer/{enrollment}/documents', [MemberFieldApiController::class, 'storeDocument'])->whereNumber('enrollment')->name('api.trainer.document.store');
});

// The worker's own side of a job: the order, what the inspector flagged, and the repair tickets.
Route::middleware(['auth:sanctum', 'idle', 'member'])->group(function () {
    Route::post('/me/photo', [MemberWorkApiController::class, 'storePhoto'])->name('api.me.photo.store');

    Route::get('/work', [MemberWorkApiController::class, 'work'])->name('api.work');
    Route::get('/work/{order}', [MemberWorkApiController::class, 'show'])->whereNumber('order')->name('api.work.show');
    Route::post('/work/{order}/accept', [MemberWorkApiController::class, 'accept'])->whereNumber('order')->name('api.work.accept');
    Route::post('/work/{order}/log', [MemberWorkApiController::class, 'log'])->whereNumber('order')->name('api.work.log');
    Route::post('/work/{order}/complete', [MemberWorkApiController::class, 'complete'])->whereNumber('order')->name('api.work.complete');
    Route::post('/work/{order}/dispute/{dispute}/answer', [MemberWorkApiController::class, 'answer'])->whereNumber('order')->whereNumber('dispute')->name('api.work.dispute.answer');
    Route::get('/work/{order}/update/{update}/photo', [MemberWorkApiController::class, 'updatePhoto'])->whereNumber('order')->whereNumber('update')->name('api.work.update.photo');
    Route::get('/work/{order}/dispute/{dispute}/evidence', [MemberWorkApiController::class, 'disputeEvidence'])->whereNumber('order')->whereNumber('dispute')->name('api.work.dispute.evidence');

    Route::get('/fixes', [MemberWorkApiController::class, 'fixes'])->name('api.fixes');
    Route::post('/fixes/{item}/proof', [MemberWorkApiController::class, 'proveFix'])->whereNumber('item')->name('api.fixes.prove');
    Route::get('/fixes/{item}/photo', [MemberWorkApiController::class, 'fixPhoto'])->whereNumber('item')->name('api.fixes.photo');

    Route::get('/tech', [MemberWorkApiController::class, 'jobs'])->name('api.tech');
    Route::post('/tech/{ticket}/resolve', [MemberWorkApiController::class, 'resolveJob'])->whereNumber('ticket')->name('api.tech.resolve');
});
