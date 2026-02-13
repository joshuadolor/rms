<?php

use App\Http\Controllers\Api\AuthController;
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

// Auth (guest) — rate limited
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth.login');
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:auth.forgot-password');
Route::post('/reset-password', ResetPasswordController::class);

// Social login — rate limited
Route::post('/auth/google', [SocialAuthController::class, 'google'])->middleware('throttle:auth.social');
Route::post('/auth/facebook', [SocialAuthController::class, 'facebook'])->middleware('throttle:auth.social');
Route::post('/auth/instagram', [SocialAuthController::class, 'instagram'])->middleware('throttle:auth.social');

// Auth (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', [AuthController::class, 'user']);
});
