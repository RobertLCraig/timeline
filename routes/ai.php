<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Mcp\Servers\TimelineServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP (Model Context Protocol) Routes
|--------------------------------------------------------------------------
|
| Remote MCP server for AI agents (Claude Desktop, Claude Code, etc.) to post
| timeline events. Authenticated via OAuth2 (Laravel Passport) with the
| `mcp:use` scope — the agent registers itself, the user logs in and consents,
| and the issued token is sent as a Bearer token to /mcp.
|
| Desktop "Add custom connector": just the Name + the URL below; Claude
| discovers the OAuth server automatically from the 401 challenge.
|   URL: https://<domain>/mcp
|
| Claude Code:  claude mcp add --transport http timeline https://<domain>/mcp
|
| (The REST API at /api/... still uses Sanctum personal access tokens.)
*/

// OAuth discovery + dynamic client registration endpoints
// (.well-known/oauth-* and /oauth/register), backed by Passport.
Mcp::oauthRoutes();

Mcp::web('/mcp', TimelineServer::class)
    ->middleware(AuthenticateMcp::class);
