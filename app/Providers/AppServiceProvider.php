<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the MCP OAuth scope at boot so it's recognised even when
        // routes are cached (routes/ai.php — where oauthRoutes() also registers
        // it — isn't loaded at runtime under route:cache).
        Passport::tokensCan([
            'mcp:use' => 'View, post, and edit events on your Family Timeline',
        ]);

        // Consent screen shown when an OAuth client (e.g. Claude) requests access.
        Passport::authorizationView('oauth.authorize');

        // OAuth access tokens for MCP are long-lived but refreshable.
        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(60));
    }
}
