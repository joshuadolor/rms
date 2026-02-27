<?php

use App\Http\Controllers\PublicRestaurantPageController;
use App\Http\Controllers\QrRedirectController;
use Illuminate\Support\Facades\Route;

// Public restaurant page: subdomain at {slug}.RESTAURANT_DOMAIN (e.g. test.rms.local).
Route::domain('{slug}.' . config('app.restaurant_domain'))->group(function () {
    Route::get('/', [PublicRestaurantPageController::class, 'show'])->name('public.restaurant');
});

// Path-based fallback so you can open http://localhost:8000/r/{slug} without subdomain (same Blade templates).
Route::get('/r/{slug}', [PublicRestaurantPageController::class, 'show'])->name('public.restaurant.path');

// QR redirect: main domain GET /page/r/{uuid} → redirect to {scheme}://{slug}.RESTAURANT_DOMAIN/
Route::get('/page/r/{uuid}', QrRedirectController::class)->name('qr.redirect');

Route::get('/', function () {
    return response()->json([
        'message' => 'Restaurant Management System API',
        'docs' => '/api/health',
        'version' => '1.0',
    ]);
});
