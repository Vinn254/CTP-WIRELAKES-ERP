<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Fallback for the (rare) non-API browser redirect when an unauthenticated
// visitor hits a protected web route. Returns a clear 401 until a real
// Blade login page is added.
Route::get('/login', function () {
    abort(401, 'Login page not implemented yet. Use /api/auth/login to obtain a token.');
})->name('login');
