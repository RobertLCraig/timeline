<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListGroupsTool;
use App\Mcp\Tools\PostTimelineEventTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Family Timeline')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
Post events to a Family Timeline group on behalf of the authenticated user.

Authenticate with a personal access token (created in the app under Profile →
API Tokens) sent as `Authorization: Bearer <token>`. The token must carry the
`events:write` ability to post.

Recommended flow:
1. Call `list_groups` to find the group slug to post into.
2. Call `list_categories` to pick a valid category name (optional).
3. Call `post_timeline_event` with the group slug, a title, and an event_date
   (YYYY-MM-DD). Other fields are optional and sensibly defaulted.
MARKDOWN)]
class TimelineServer extends Server
{
    protected array $tools = [
        PostTimelineEventTool::class,
        ListGroupsTool::class,
        ListCategoriesTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
