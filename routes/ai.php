<?php

use App\Mcp\Servers\EiServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| The Everything Immersive MCP server: lets AI clients (Claude, etc.)
| create organizers and event drafts on behalf of an authenticated user.
| Auth is a Sanctum personal access token (created at /settings/api-tokens)
| carrying the 'mcp' ability, sent as an Authorization: Bearer header.
|
| Note this route is intentionally NOT in the maintenance-mode allow-list
| in bootstrap/app.php, so all MCP access 503s while the site is down.
|
*/

Mcp::web('/mcp', EiServer::class)
    ->middleware(['throttle:60,1', 'auth:sanctum', 'abilities:mcp'])
    ->name('mcp.ei');
