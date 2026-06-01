<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Support\EventCreator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Edit an existing Family Timeline event. Only the fields you provide are changed. You must be the event creator or a group admin/owner.')]
class UpdateTimelineEventTool extends Tool
{
    protected string $name = 'update_timeline_event';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'title' => 'sometimes|string|max:200',
            'event_date' => 'sometimes|date|before_or_equal:'.now()->addYear()->toDateString(),
            'description' => 'sometimes|nullable|string|max:5000',
            'category' => 'sometimes|nullable|string',
            'visibility' => 'sometimes|in:public,members,private',
            'social_visibility' => 'sometimes|in:family,close_friends,friends,acquaintances,public,private',
            'image_url' => 'sometimes|nullable|string|max:500',
            'album_url' => 'sometimes|nullable|url|max:1000',
        ]);

        $event = Event::with('group')->find($validated['event_id']);
        if (! $event) {
            return Response::error("Event #{$validated['event_id']} not found.");
        }

        $group = $event->group;

        // Same edit rule as the REST endpoint: creator, group admin/owner, or super admin.
        $canEdit = $event->created_by === $user->id
            || $group->isAdminOrOwner($user->id)
            || $user->isSuperAdmin();

        if (! $canEdit) {
            return Response::error('You do not have permission to edit this event.');
        }

        // Build the partial update from the fields actually provided.
        $data = $validated;
        unset($data['event_id']);

        if (array_key_exists('category', $data)) {
            $data['category_id'] = EventCreator::resolveCategoryId($data['category']);
            unset($data['category']);
        }

        // An explicit social_visibility from an agent is treated as an override.
        if (array_key_exists('social_visibility', $data)) {
            $data['visibility_is_override'] = true;
        }

        $event = EventCreator::applyUpdate($event, $user, $data);

        return Response::json([
            'id' => $event->id,
            'title' => $event->title,
            'event_date' => $event->event_date->toDateString(),
            'group' => $group->slug,
            'category' => $event->category?->name,
            'visibility' => $event->visibility,
            'social_visibility' => $event->social_visibility,
            'url' => url("/g/{$group->slug}"),
            'message' => 'Event updated.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()
                ->description('The id of the event to edit (returned by post_timeline_event or list calls).')
                ->required(),
            'title' => $schema->string()
                ->description('New title (max 200 chars).'),
            'event_date' => $schema->string()
                ->description('New event date as YYYY-MM-DD.'),
            'description' => $schema->string()
                ->description('New description (max 5000 chars).'),
            'category' => $schema->string()
                ->description('New category name (from list_categories), case-insensitive.'),
            'visibility' => $schema->string()
                ->description('Membership visibility: public, members, or private.'),
            'social_visibility' => $schema->string()
                ->description('Social tier: family, close_friends, friends, acquaintances, public, or private.'),
            'image_url' => $schema->string()
                ->description('New image URL or upload path.'),
            'album_url' => $schema->string()
                ->description('New URL to a full photo album.'),
        ];
    }
}
