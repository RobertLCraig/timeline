<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VisibilityController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Public routes (no auth required) ────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:login');

    // Password reset
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:login');

    // MFA verification during login (session-scoped challenge, no auth middleware needed)
    Route::post('/mfa/verify', [AuthController::class, 'mfaVerify'])->middleware('throttle:login');

    // Google OAuth routes live in routes/web.php (need web session middleware — see note there)

    // Email verification — signed URL from notification email
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/visibility/tiers', [VisibilityController::class, 'tiers']);

// Group public page & public events (optional auth for visibility — uses Auth::guard('sanctum'))
Route::get('/groups/{slug}', [GroupController::class, 'show']);
Route::get('/groups/{slug}/events', [EventController::class, 'index']);
Route::get('/groups/{slug}/events/{id}', [EventController::class, 'show']);

// ── Authenticated routes ────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/active-group', [AuthController::class, 'setActiveGroup']);

    // MFA management (requires active session)
    Route::post('/auth/mfa/enable',  [AuthController::class, 'mfaEnable']);
    Route::post('/auth/mfa/confirm', [AuthController::class, 'mfaConfirm']);
    Route::post('/auth/mfa/disable', [AuthController::class, 'mfaDisable']);

    // Email verification resend
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');

    // GDPR: data export and account deletion
    Route::get('/me/export', [AuthController::class, 'export']);
    Route::delete('/me', [AuthController::class, 'deleteAccount']);

    // Uploads
    Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:upload');

    // Groups - user's own
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);

    // Join or leave a group
    Route::post('/groups/join-by-code', [GroupController::class, 'joinByCode']);
    Route::post('/groups/{slug}/join', [GroupController::class, 'join']);
    Route::delete('/groups/{slug}/leave', [GroupController::class, 'leave']);

    // Social visibility settings
    Route::get('/visibility/categories', [VisibilityController::class, 'categoryDefaults']);
    Route::put('/visibility/categories/{categoryId}', [VisibilityController::class, 'updateCategoryDefault']);
    Route::get('/visibility/groups', [VisibilityController::class, 'groupVisibility']);
    Route::put('/visibility/groups/{groupId}', [VisibilityController::class, 'updateGroupVisibility']);

    // Group member routes (requires group membership)
    Route::middleware('group.role:owner,admin,member')->group(function () {
        Route::get('/groups/{slug}/members', [GroupController::class, 'members']);
        Route::post('/groups/{slug}/events', [EventController::class, 'store']);
        Route::put('/groups/{slug}/events/{id}', [EventController::class, 'update']);
        Route::delete('/groups/{slug}/events/{id}', [EventController::class, 'destroy']);
    });

    // Group admin routes (requires admin or owner)
    Route::middleware('group.role:owner,admin')->group(function () {
        Route::put('/groups/{slug}', [GroupController::class, 'update']);
        Route::put('/groups/{slug}/members/{userId}', [GroupController::class, 'updateMember']);
        Route::delete('/groups/{slug}/members/{userId}', [GroupController::class, 'removeMember']);
        Route::post('/groups/{slug}/invites', [GroupController::class, 'createInvite']);
        Route::get('/groups/{slug}/invites', [GroupController::class, 'invites']);
        Route::delete('/groups/{slug}/invites/{id}', [GroupController::class, 'deleteInvite']);
    });

    // Group owner routes
    Route::middleware('group.role:owner')->group(function () {
        Route::delete('/groups/{slug}', [GroupController::class, 'destroy']);
    });

    // Super admin routes
    Route::prefix('admin')->middleware('super_admin')->group(function () {
        Route::get('/referral-codes', [AdminController::class, 'referralCodes']);
        Route::post('/referral-codes', [AdminController::class, 'createReferralCode']);
        Route::delete('/referral-codes/{id}', [AdminController::class, 'deleteReferralCode']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}/role', [AdminController::class, 'updateUserRole']);

        // NSFW settings
        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);

        // Content moderation queue
        Route::get('/upload-flags', [AdminController::class, 'uploadFlags']);
        Route::put('/upload-flags/{id}', [AdminController::class, 'reviewFlag']);
    });
});
