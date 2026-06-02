<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get the full details of a single event by event_id, including description, photos, visibility, and whether you can edit it.')]
class GetTimelineEventTool extends Tool
{
    protected string $name = 'get_timeline_event';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'event_id' => 'required|integer',
        ]);

        $event = Event::with(['group', 'category:id,name', 'creator:id,name'])->find($validated['event_id']);
        if (! $event || ! $event->isVisibleTo($user)) {
            return Response::error("Event #{$validated['event_id']} not found or you don't have access to it.");
        }

        $group = $event->group;
        $canEdit = $event->created_by === $user->id
            || $group->isAdminOrOwner($user->id)
            || $user->isSuperAdmin();

        return Response::json([
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'event_date' => $event->event_date->toDateString(),
            'group' => $group->slug,
            'category' => $event->category?->name,
            'visibility' => $event->visibility,
            'social_visibility' => $event->social_visibility,
            'image_url' => $event->image_url,
            'album_url' => $event->album_url,
            'created_by' => $event->creator?->name,
            'source' => $event->source,
            'can_edit' => $canEdit,
            'url' => url("/g/{$group->slug}"),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()
                ->description('The id of the event to read (from list_timeline_events or a post/update result).')
                ->required(),
        ];
    }
}
