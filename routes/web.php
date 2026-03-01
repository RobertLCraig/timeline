<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Google OAuth redirect + callback must live here (web middleware group)
 * rather than in routes/api.php.
 *
 * Reason: Sanctum's statefulApi() only starts a session for requests that
 * originate from the SPA domain. The OAuth callback arrives from Google's
 * servers, so the session middleware is never applied for API routes —
 * causing "Session store not set on request." The web group runs
 * StartSession unconditionally, which is what Socialite requires to
 * store and verify the OAuth state parameter.
 *
 * URLs are intentionally kept under /api/auth/... so GOOGLE_REDIRECT_URI
 * in .env and the Google OAuth app config do not need to change.
 */
Route::prefix('api/auth/oauth/google')->group(function () {
    Route::get('/redirect', [AuthController::class, 'googleRedirect']);
    Route::get('/callback', [AuthController::class, 'googleCallback']);
});

// Catch-all route for the SPA (must be last)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
