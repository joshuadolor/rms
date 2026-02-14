<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Restaurant Management System API',
        'timestamp' => now()->toIso8601String(),
    ]);
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

// Email verification (signed URL from verification email; no auth required)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('api.verification.verify');

// Resend verification: guest (email in body) or authenticated
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth.forgot-password');

// Social login — rate limited
Route::post('/auth/google', [SocialAuthController::class, 'google'])->middleware('throttle:auth.social');
Route::post('/auth/facebook', [SocialAuthController::class, 'facebook'])->middleware('throttle:auth.social');
Route::post('/auth/instagram', [SocialAuthController::class, 'instagram'])->middleware('throttle:auth.social');

// Auth (protected) — require verified email
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', [AuthController::class, 'user']);
});
