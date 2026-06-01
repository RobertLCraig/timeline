<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'group.role' => \App\Http\Middleware\EnsureGroupRole::class,
            'resolve.group' => \App\Http\Middleware\ResolveGroup::class,
            // Sanctum token ability checks (no-op for SPA cookie sessions).
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booted(function () {
        // Global API throttle: 300 requests per minute per IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // Strict throttle for login: 5 attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Strict throttle for registration: 3 attempts per minute per IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Upload throttle: 20 per minute per authenticated user (fallback to IP)
        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?? $request->ip());
        });

        // Event-write throttle: 60 writes/min, keyed per token (agents) so a
        // runaway agent can't flood a timeline. SPA sessions key by user id.
        RateLimiter::for('events-write', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            $key = ($token instanceof \Laravel\Sanctum\PersonalAccessToken)
                ? 'tok:'.$token->getKey()
                : 'usr:'.($request->user()?->id ?? $request->ip());

            return Limit::perMinute(60)->by($key);
        });
    })
    ->create();
