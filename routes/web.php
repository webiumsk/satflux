<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\OgImageController;
use Illuminate\Support\Facades\Route;

// OG Image for social media sharing
Route::get('/og-image.png', [OgImageController::class, 'generate']);

// OG Image for social media sharing (must be before catch-all route)
Route::get('/og-image.png', [OgImageController::class, 'generate']);

// Named route for authentication redirects (used by Laravel auth middleware)
// Redirects to SPA login page (handled by Vue Router)
Route::get('/login', function () {
    return view('app');
})->name('login');

// Verification route for Vue router (redirects to SPA, component handles API call)
Route::get('/auth/verify-email/{id}/{hash}', function () {
    return view('app');
})->where(['id' => '[0-9]+', 'hash' => '[a-f0-9]+']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');




