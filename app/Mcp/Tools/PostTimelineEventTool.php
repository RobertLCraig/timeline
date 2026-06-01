<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use App\Support\EventCreator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new event on a Family Timeline group. Requires the events:write token ability and membership of the target group.')]
class PostTimelineEventTool extends Tool
{
    protected string $name = 'post_timeline_event';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'required|string',
            'title' => 'required|string|max:200',
            'event_date' => 'required|date|before_or_equal:'.now()->addYear()->toDateString(),
            'description' => 'nullable|string|max:5000',
            'category' => 'nullable|string',
            'visibility' => 'nullable|in:public,members,private',
            'social_visibility' => 'nullable|in:family,close_friends,friends,acquaintances,public,private',
            'image_url' => 'nullable|string|max:500',
            'album_url' => 'nullable|url|max:1000',
        ]);

        $group = Group::where('slug', $validated['group'])->first();
        if (! $group) {
            return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
        }

        // Same membership rule as the REST group.role middleware.
        if (! $user->isSuperAdmin() && $group->getMemberRole($user->id) === null) {
            return Response::error('You are not a member of that group.');
        }

        $categoryId = EventCreator::resolveCategoryId($validated['category'] ?? null);

        $event = EventCreator::create($user, $group, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'category_id' => $categoryId,
            'visibility' => $validated['visibility'] ?? 'members',
            'social_visibility' => $validated['social_visibility'] ?? null,
            // An explicit social_visibility from an agent is treated as an override.
            'visibility_is_override' => isset($validated['social_visibility']),
            'image_url' => $validated['image_url'] ?? null,
            'album_url' => $validated['album_url'] ?? null,
        ], 'mcp');

        return Response::json([
            'id' => $event->id,
            'title' => $event->title,
            'event_date' => $event->event_date->toDateString(),
            'group' => $group->slug,
            'category' => $event->category?->name,
            'visibility' => $event->visibility,
            'social_visibility' => $event->social_visibility,
            'url' => url("/g/{$group->slug}"),
            'message' => 'Event created.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('The target group slug (from list_groups).')
                ->required(),
            'title' => $schema->string()
                ->description('Short event title (max 200 chars).')
                ->required(),
            'event_date' => $schema->string()
                ->description('Event date as YYYY-MM-DD. Must be on or before one year from today.')
                ->required(),
            'description' => $schema->string()
                ->description('Optional longer description (max 5000 chars).'),
            'category' => $schema->string()
                ->description('Optional category name (from list_categories), e.g. "Travel". Case-insensitive.'),
            'visibility' => $schema->string()
                ->description('Membership visibility: public, members (default), or private.'),
            'social_visibility' => $schema->string()
                ->description('Social tier: family, close_friends, friends, acquaintances, public, or private. Defaults from the category if omitted.'),
            'image_url' => $schema->string()
                ->description('Optional image URL or upload path.'),
            'album_url' => $schema->string()
                ->description('Optional URL to a full photo album.'),
        ];
    }
}
