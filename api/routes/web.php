<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Restaurant Management System API',
        'docs' => '/api/health',
        'version' => '1.0',
    ]);
});
