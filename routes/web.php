<?php

use Illuminate\Support\Facades\Route;

// API routes are handled in routes/api.php automatically by Laravel

// Catch-all route for the SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
