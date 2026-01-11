<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StoreChecklistController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\EnsureStoreOwnership;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Authentication routes (rate limited)
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/auth/register', [RegisterController::class, 'register']);
    Route::post('/auth/login', [LoginController::class, 'login']);
    Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    
    // Password reset
    Route::post('/auth/password/reset-link', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/auth/password/reset', [PasswordResetController::class, 'reset']);
    
    // LNURL-auth
    Route::post('/lnurl-auth/challenge', [LnurlAuthController::class, 'challenge']);
    Route::post('/lnurl-auth/verify', [LnurlAuthController::class, 'verify']);
});

// Authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    // User/Account routes
    Route::get('/user', [AccountController::class, 'user']);
    Route::put('/user', [AccountController::class, 'updateProfile']);
    Route::put('/user/password', [AccountController::class, 'updatePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/{store}', [StoreController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);

    // Store Checklist
    Route::get('/stores/{store}/checklist', [StoreChecklistController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/checklist/{itemKey}', [StoreChecklistController::class, 'update'])
        ->middleware(EnsureStoreOwnership::class);

    // Store Settings
    Route::get('/stores/{store}/settings', [StoreSettingsController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/settings', [StoreSettingsController::class, 'update'])
        ->middleware(EnsureStoreOwnership::class);
});
