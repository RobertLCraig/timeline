<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\CreateGroupInviteTool;
use App\Mcp\Tools\DeleteTimelineEventTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListGroupMembersTool;
use App\Mcp\Tools\ListGroupsTool;
use App\Mcp\Tools\ListTimelineEventsTool;
use App\Mcp\Tools\PostTimelineEventTool;
use App\Mcp\Tools\UpdateTimelineEventTool;
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
4. To edit or delete an event, first call `list_timeline_events` (search by
   text/date/category) to find its event_id, then call `update_timeline_event`
   or `delete_timeline_event` with that id. Never guess an event_id.

Photos: set `image_url` (a single photo) and/or `album_url` (a link to a full
album) when posting or updating an event. On update, pass an empty string to
remove one. You can also `create_category`, `list_group_members`, and
`create_group_invite` (owners/admins only).
MARKDOWN)]
class TimelineServer extends Server
{
    protected array $tools = [
        PostTimelineEventTool::class,
        UpdateTimelineEventTool::class,
        DeleteTimelineEventTool::class,
        ListTimelineEventsTool::class,
        ListGroupsTool::class,
        ListCategoriesTool::class,
        CreateCategoryTool::class,
        ListGroupMembersTool::class,
        CreateGroupInviteTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
