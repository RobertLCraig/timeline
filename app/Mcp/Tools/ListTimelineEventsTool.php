<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Models\Group;
use App\Models\UserGroupVisibility;
use App\Support\EventCreator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Find events in a group and their ids, so you can edit or delete a specific one. Supports text search and date/category filters.')]
class ListTimelineEventsTool extends Tool
{
    protected string $name = 'list_timeline_events';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'required|string',
            'query' => 'sometimes|nullable|string|max:200',
            'category' => 'sometimes|nullable|string',
            'date_from' => 'sometimes|nullable|date',
            'date_to' => 'sometimes|nullable|date',
            'mine_only' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $group = Group::where('slug', $validated['group'])->first();
        if (! $group) {
            return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
        }

        if (! $user->isSuperAdmin() && $group->getMemberRole($user->id) === null) {
            return Response::error('You are not a member of that group.');
        }

        $isManager = $user->isSuperAdmin() || $group->isAdminOrOwner($user->id);

        $q = Event::where('group_id', $group->id)->with('category:id,name');

        // Visibility — mirror EventController::index for non-managers so the
        // tool never discloses events the web timeline would hide.
        if (! $isManager) {
            // Step 1: legacy membership visibility (public/members + own).
            $q->where(function ($w) use ($user) {
                $w->whereIn('visibility', ['public', 'members'])
                    ->orWhere('created_by', $user->id);
            });

            // Step 2: social-tier filter based on how this user classifies the group.
            $groupTier = UserGroupVisibility::where('user_id', $user->id)
                ->where('group_id', $group->id)
                ->value('visibility_tier') ?? 'friends';
            $visibleTiers = Event::visibleTiersForGroupTier($groupTier);

            $q->where(function ($w) use ($visibleTiers, $user) {
                $w->whereIn('social_visibility', $visibleTiers)
                    ->orWhere(function ($w2) use ($user) {
                        $w2->where('social_visibility', 'private')
                            ->where('created_by', $user->id);
                    });
            });
        }

        if (! empty($validated['mine_only'])) {
            $q->where('created_by', $user->id);
        }

        if (! empty($validated['query'])) {
            $term = '%'.mb_strtolower($validated['query']).'%';
            $q->where(function ($w) use ($term) {
                $w->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        if (! empty($validated['category'])) {
            $q->where('category_id', EventCreator::resolveCategoryId($validated['category'], $group->id));
        }

        if (! empty($validated['date_from'])) {
            $q->where('event_date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $q->where('event_date', '<=', $validated['date_to']);
        }

        $limit = $validated['limit'] ?? 25;

        $events = $q->orderBy('event_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'event_date' => $e->event_date->toDateString(),
                'category' => $e->category?->name,
                'visibility' => $e->visibility,
                'can_edit' => $isManager || $e->created_by === $user->id,
            ]);

        return Response::json([
            'group' => $group->slug,
            'count' => $events->count(),
            'limit' => $limit,
            'events' => $events,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('The group slug to search within (from list_groups).')
                ->required(),
            'query' => $schema->string()
                ->description('Optional text to match in the title or description (case-insensitive).'),
            'category' => $schema->string()
                ->description('Optional category name to filter by.'),
            'date_from' => $schema->string()
                ->description('Optional earliest event date, YYYY-MM-DD.'),
            'date_to' => $schema->string()
                ->description('Optional latest event date, YYYY-MM-DD.'),
            'mine_only' => $schema->boolean()
                ->description('If true, only return events you created (the ones you can always edit).'),
            'limit' => $schema->integer()
                ->description('Max results to return (1-100, default 25).'),
        ];
    }
}
