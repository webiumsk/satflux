<?php

use App\Http\Controllers\OgImageController;
use App\Http\Controllers\StoreAppPageController;
use App\Http\Middleware\EnsureStoreOwnership;
use Illuminate\Support\Facades\Route;

// OG Image for social media sharing
Route::get('/og-image.png', [OgImageController::class, 'generate']);

// Named route for authentication redirects (used by Laravel auth middleware)
Route::get('/login', function () {
    return view('app');
})->name('login');

// Verification route for Vue router (redirects to SPA, component handles API call)
Route::get('/auth/verify-email/{id}/{hash}', function () {
    return view('app');
})->where(['id' => '[0-9]+', 'hash' => '[a-f0-9]+']);

// Inertia routes: store apps (must be before SPA catch-all)
Route::middleware(['auth'])->group(function () {
    Route::get('/stores/{store}/apps', [StoreAppPageController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}/apps/create', [StoreAppPageController::class, 'create'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/apps', [StoreAppPageController::class, 'store'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}/apps/{app}', [StoreAppPageController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
});

// SPA fallback (Vue Router handles all other routes)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');




