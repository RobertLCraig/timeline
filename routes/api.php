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
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
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

    // Uploads
    Route::post('/upload', [UploadController::class, 'store']);

    // Groups - user's own
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);

    // Join or leave a group
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
    });
});
