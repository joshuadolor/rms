<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PublicFeedbackController;
use App\Http\Controllers\Api\PublicRestaurantController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MenuItemTagController;
use App\Http\Controllers\Api\UserMenuItemController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RestaurantLanguageController;
use App\Http\Controllers\Api\RestaurantTranslationController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\TranslateController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Restaurant Management System API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Supported locales for restaurant languages (and app i18n). Public.
Route::get('/locales', function () {
    return response()->json(['data' => config('locales.supported', ['en', 'nl', 'ru'])]);
});

// Dev only: send a test email to verify Mailhog. Always registered; returns 404 when not local.
$testEmailHandler = function () {
    if (! app()->environment('local')) {
        abort(404);
    }
    $mailer = config('mail.default');
    $host = config('mail.mailers.smtp.host');
    $port = config('mail.mailers.smtp.port');
    try {
        \Illuminate\Support\Facades\Mail::raw('RMS test email at ' . now()->toIso8601String(), function ($m) {
            $m->to('test@rms.local')->subject('RMS test');
        });
        return response()->json([
            'message' => 'Test email sent. Check Mailhog at http://localhost:8025',
            'mail_driver' => $mailer,
            'smtp_host' => $host,
            'smtp_port' => $port,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => 'Failed to send test email',
            'mail_driver' => $mailer,
            'smtp_host' => $host,
            'smtp_port' => $port,
            'exception' => $e->getMessage(),
        ], 500);
    }
};
Route::get('/test-mail', $testEmailHandler);
Route::get('/test-email', $testEmailHandler);

// Auth (guest) — rate limited
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth.login');
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:auth.forgot-password');
Route::post('/reset-password', ResetPasswordController::class);

// Email verification (signed URL; uses uuid so internal id is not exposed)
Route::get('/email/verify/{uuid}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('api.verification.verify');

// New email verification (after profile email change; signed link sent to new address)
Route::get('/email/verify-new/{uuid}/{hash}', [EmailVerificationController::class, 'verifyNewEmail'])
    ->middleware('signed')
    ->name('api.verification.verify-new');

// Resend verification: guest (email in body) or authenticated
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth.forgot-password');

// Social login — rate limited
Route::post('/auth/google', [SocialAuthController::class, 'google'])->middleware('throttle:auth.social');
Route::post('/auth/facebook', [SocialAuthController::class, 'facebook'])->middleware('throttle:auth.social');
Route::post('/auth/instagram', [SocialAuthController::class, 'instagram'])->middleware('throttle:auth.social');

// Restaurant media (public — for <img> src)
Route::get('/restaurants/{uuid}/logo', [RestaurantController::class, 'serveLogo']);
Route::get('/restaurants/{uuid}/banner', [RestaurantController::class, 'serveBanner']);

// Public restaurant page by slug (no auth). For [slug].domain.com and /r/:slug.
Route::get('/public/restaurants/{slug}', [PublicRestaurantController::class, 'show']);

// Public feedback submission (no auth); rate-limited.
Route::post('/public/restaurants/{slug}/feedback', [PublicFeedbackController::class, 'store'])
    ->middleware('throttle:feedback');

// Auth (protected) — require verified email
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::match(['patch', 'put'], '/user', [ProfileController::class, 'update']);
    Route::post('/profile/password', [ProfileController::class, 'changePassword']);

    // Restaurants (owner CRUD + media upload)
    Route::apiResource('restaurants', RestaurantController::class)->parameters(['restaurant' => 'uuid']);
    Route::post('/restaurants/{uuid}/logo', [RestaurantController::class, 'uploadLogo'])->name('restaurants.upload-logo');
    Route::post('/restaurants/{uuid}/banner', [RestaurantController::class, 'uploadBanner'])->name('restaurants.upload-banner');

    // Feedbacks (owner: list, approve/reject, delete)
    Route::get('/restaurants/{restaurant}/feedbacks', [FeedbackController::class, 'index']);
    Route::match(['put', 'patch'], '/restaurants/{restaurant}/feedbacks/{feedback}', [FeedbackController::class, 'update']);
    Route::delete('/restaurants/{restaurant}/feedbacks/{feedback}', [FeedbackController::class, 'destroy']);

    // Restaurant languages (install / uninstall locales per restaurant)
    Route::get('/restaurants/{restaurant}/languages', [RestaurantLanguageController::class, 'index']);
    Route::post('/restaurants/{restaurant}/languages', [RestaurantLanguageController::class, 'store']);
    Route::delete('/restaurants/{restaurant}/languages/{locale}', [RestaurantLanguageController::class, 'destroy']);

    // Restaurant translations (description per locale)
    Route::get('/restaurants/{restaurant}/translations', [RestaurantTranslationController::class, 'index']);
    Route::get('/restaurants/{restaurant}/translations/{locale}', [RestaurantTranslationController::class, 'show']);
    Route::match(['put', 'patch'], '/restaurants/{restaurant}/translations/{locale}', [RestaurantTranslationController::class, 'update']);

    // Menus (CRUD + reorder; active menus show on public website)
    Route::get('/restaurants/{restaurant}/menus', [MenuController::class, 'index']);
    Route::post('/restaurants/{restaurant}/menus', [MenuController::class, 'store']);
    Route::get('/restaurants/{restaurant}/menus/{menu}', [MenuController::class, 'show']);
    Route::match(['put', 'patch'], '/restaurants/{restaurant}/menus/{menu}', [MenuController::class, 'update']);
    Route::delete('/restaurants/{restaurant}/menus/{menu}', [MenuController::class, 'destroy']);
    Route::post('/restaurants/{restaurant}/menus/reorder', [MenuController::class, 'reorder']);

    // Categories (per menu; CRUD + reorder; translations per restaurant locale)
    Route::get('/restaurants/{restaurant}/menus/{menu}/categories', [CategoryController::class, 'index']);
    Route::post('/restaurants/{restaurant}/menus/{menu}/categories', [CategoryController::class, 'store']);
    Route::get('/restaurants/{restaurant}/menus/{menu}/categories/{category}', [CategoryController::class, 'show']);
    Route::match(['put', 'patch'], '/restaurants/{restaurant}/menus/{menu}/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/restaurants/{restaurant}/menus/{menu}/categories/{category}', [CategoryController::class, 'destroy']);
    Route::post('/restaurants/{restaurant}/menus/{menu}/categories/reorder', [CategoryController::class, 'reorder']);

    // Menu item tags (list default only; POST/PATCH/DELETE return 403 — custom tags disabled)
    Route::get('/menu-item-tags', [MenuItemTagController::class, 'index']);
    Route::post('/menu-item-tags', [MenuItemTagController::class, 'store']);
    Route::match(['put', 'patch'], '/menu-item-tags/{tag}', [MenuItemTagController::class, 'update']);
    Route::delete('/menu-item-tags/{tag}', [MenuItemTagController::class, 'destroy']);

    // Menu items — user-level (standalone list/create + get/update/delete any owned item)
    Route::get('/menu-items', [UserMenuItemController::class, 'index']);
    Route::post('/menu-items', [UserMenuItemController::class, 'store']);
    Route::get('/menu-items/{item}', [UserMenuItemController::class, 'show']);
    Route::match(['put', 'patch'], '/menu-items/{item}', [UserMenuItemController::class, 'update']);
    Route::delete('/menu-items/{item}', [UserMenuItemController::class, 'destroy']);

    // Menu items — restaurant-scoped (CRUD with translations; optional category; reorder within category)
    Route::get('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'index']);
    Route::post('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'store']);
    Route::get('/restaurants/{restaurant}/menu-items/{item}', [MenuItemController::class, 'show']);
    Route::match(['put', 'patch'], '/restaurants/{restaurant}/menu-items/{item}', [MenuItemController::class, 'update']);
    Route::delete('/restaurants/{restaurant}/menu-items/{item}', [MenuItemController::class, 'destroy']);
    Route::post('/restaurants/{restaurant}/categories/{category}/menu-items/reorder', [MenuItemController::class, 'reorder']);

    // Machine translation (LibreTranslate when configured); rate-limited
    Route::post('/translate', TranslateController::class)->middleware('throttle:translate');
});
