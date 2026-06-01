<?php

use App\Mcp\Servers\TimelineServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP (Model Context Protocol) Routes
|--------------------------------------------------------------------------
|
| Remote MCP server for AI agents (Claude, Ollama, etc.) to post timeline
| events. Authenticated with the same Sanctum personal access tokens used by
| the REST API. Per-tool ability checks (events:write, groups:read,
| categories:read) are enforced inside each tool.
|
| Connect with:
|   claude mcp add --transport http timeline https://<domain>/mcp \
|       --header "Authorization: Bearer <token>"
*/

Mcp::web('/mcp', TimelineServer::class)
    ->middleware('auth:sanctum');
