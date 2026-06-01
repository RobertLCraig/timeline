<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates MCP requests via the Passport OAuth2 token (api guard) and
 * requires the `mcp:use` scope.
 *
 * Why a custom middleware instead of `auth:api`: Laravel's auth middleware
 * THROWS on failure, which unwinds the stack before laravel/mcp's
 * AddWwwAuthenticateHeader can append the WWW-Authenticate header. That header
 * is what tells the MCP client where to discover the OAuth server. By
 * RETURNING a 401 response here, the header middleware (which wraps us) sees
 * the 401 and adds the discovery hint, so the OAuth flow can begin.
 */
class AuthenticateMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user();

        if (! $user) {
            return response()->json([
                'error' => 'unauthorized',
                'error_description' => 'A valid OAuth access token is required.',
            ], 401);
        }

        if (! $user->tokenCan('mcp:use')) {
            return response()->json([
                'error' => 'insufficient_scope',
                'error_description' => 'The token is missing the required "mcp:use" scope.',
            ], 403);
        }

        // Make the resolved user available to the MCP tools.
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
