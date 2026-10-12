<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CTP-WireLakes API routes
|--------------------------------------------------------------------------
| Consumed by both the Blade web app and the Flutter mobile app.
| Auth is via Laravel Sanctum personal access tokens.
*/

// Health check — used by ops, load balancers and the Flutter splash screen
Route::get('/health', [HealthController::class, 'index'])->name('api.health');

// Public auth endpoints
Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
Route::post('/auth/login',    [AuthController::class, 'login'])->name('api.auth.login');

// Protected endpoints (require a valid Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',       [AuthController::class, 'me'])->name('api.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
});
