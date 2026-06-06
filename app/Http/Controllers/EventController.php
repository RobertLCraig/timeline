<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Models\UserGroupVisibility;
use App\Support\EventCreator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class EventController extends Controller
{
    /**
     * GET /api/groups/{slug}/events
     *
     * Filters events by:
     * 1. Old visibility (public/members/private) — who can see the group at all
     * 2. Social visibility tier — based on how the viewer classifies this group
     */
    public function index(Request $request, string $slug)
    {
        $group = Group::where('slug', $slug)->first();
        if (! $group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        // Resolve user via Sanctum guard (works on public routes too)
        $user = Auth::guard('sanctum')->user();
        $isMember = false;
        $isAdminOrOwner = false;
        $memberRole = null;

        if ($user) {
            $memberRole = $group->getMemberRole($user->id);
            $isMember = $memberRole !== null;
            $isAdminOrOwner = in_array($memberRole, ['owner', 'admin']) || $user->isSuperAdmin();
        }

        $query = Event::where('group_id', $group->id)
            ->with(['category', 'creator:id,name,avatar_url']);

        // ── Step 1: Old membership visibility filter ────────────────────────
        if (! $user) {
            $query->where('visibility', 'public');
        } elseif ($isAdminOrOwner) {
            // Admin/owner sees everything (no old-visibility filter)
        } elseif ($isMember) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('visibility', ['public', 'members'])
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'private')
                            ->where('created_by', $user->id);
                    });
            });
        } else {
            $query->where('visibility', 'public');
        }

        // ── Step 2: Social visibility tier filter (members only) ────────────
        if ($isMember && ! $isAdminOrOwner) {
            // Get the user's social tier for this group (default: 'friends')
            $groupTierRecord = UserGroupVisibility::where('user_id', $user->id)
                ->where('group_id', $group->id)
                ->first();
            $groupTier = $groupTierRecord?->visibility_tier ?? 'friends';

            $visibleTiers = Event::visibleTiersForGroupTier($groupTier);

            // Events visible if:
            // - social_visibility is in the visible tiers, OR
            // - it's 'private' and the user is the creator (always sees own private)
            $query->where(function ($q) use ($visibleTiers, $user) {
                $q->whereIn('social_visibility', $visibleTiers)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('social_visibility', 'private')
                            ->where('created_by', $user->id);
                    });
            });
        }

        // ── Optional filters ────────────────────────────────────────────────
        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }
        if ($request->has('date_from')) {
            $query->where('event_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('event_date', '<=', $request->date_to);
        }

        $sortDir = $request->input('sort', 'desc');
        $events = $query->orderBy('event_date', $sortDir)
            ->paginate($request->input('per_page', 50));

        return response()->json($events);
    }

    /**
     * GET /api/groups/{slug}/events/{id}
     */
    public function show(Request $request, string $slug, int $id)
    {
        $group = Group::where('slug', $slug)->first();
        if (! $group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $event = Event::where('id', $id)
            ->where('group_id', $group->id)
            ->with(['category', 'creator:id,name,avatar_url'])
            ->first();

        if (! $event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = Auth::guard('sanctum')->user();

        if (! $event->isVisibleTo($user)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        return response()->json(['event' => $event]);
    }

    /**
     * POST /api/groups/{slug}/events
     */
    public function store(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');

        // Agent convenience: accept a category by name when no id is given.
        if (! $request->filled('category_id') && $request->filled('category')) {
            $request->merge(['category_id' => EventCreator::resolveCategoryId($request->input('category'), $group->id)]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'event_date' => 'required|date|before_or_equal:'.now()->addYear()->toDateString(),
            'category_id' => 'nullable|integer|exists:event_categories,id',
            'visibility' => 'sometimes|in:public,members,private',
            'social_visibility' => 'sometimes|nullable|in:family,close_friends,friends,acquaintances,public,private',
            'visibility_is_override' => 'sometimes|boolean',
            'image_url' => 'nullable|string|max:500',
            'album_url' => 'nullable|url|max:1000',
            'import_hash' => 'sometimes|nullable|string|max:64',
        ]);

        $user = $request->user();

        // Idempotent import: a repeated import_hash updates the existing event
        // (subject to the same ownership rule as a normal edit) instead of
        // creating a duplicate, so the photo-import pipeline can be re-run.
        [$event, $created] = EventCreator::importUpsert(
            $user,
            $group,
            $validated,
            $this->requestSource($request),
            fn (Event $existing) => $existing->created_by === $user->id
                || $group->isAdminOrOwner($user->id)
                || $user->isSuperAdmin(),
        );

        return response()->json(['event' => $event], $created ? 201 : 200);
    }

    /**
     * Determine how this write arrived: a real personal access token => 'api',
     * an SPA session (TransientToken) => 'web'. MCP writes go through the
     * EventCreator service directly with source 'mcp'.
     */
    private function requestSource(Request $request): string
    {
        return $request->user()?->currentAccessToken() instanceof PersonalAccessToken ? 'api' : 'web';
    }

    /**
     * PUT /api/groups/{slug}/events/{id}
     */
    public function update(Request $request, string $slug, int $id)
    {
        $group = $request->attributes->get('group');

        $event = Event::where('id', $id)->where('group_id', $group->id)->first();

        if (! $event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();
        if ($event->created_by !== $user->id && ! $group->isAdminOrOwner($user->id) && ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'You do not have permission to edit this event.'], 403);
        }

        // Agent convenience: accept a category by name when no id is given.
        if (! $request->filled('category_id') && $request->filled('category')) {
            $request->merge(['category_id' => EventCreator::resolveCategoryId($request->input('category'), $group->id)]);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|nullable|string|max:5000',
            'event_date' => 'sometimes|date|before_or_equal:'.now()->addYear()->toDateString(),
            'category_id' => 'sometimes|nullable|integer|exists:event_categories,id',
            'visibility' => 'sometimes|in:public,members,private',
            'social_visibility' => 'sometimes|nullable|in:family,close_friends,friends,acquaintances,public,private',
            'visibility_is_override' => 'sometimes|boolean',
            'image_url' => 'sometimes|nullable|string|max:500',
            'album_url' => 'sometimes|nullable|url|max:1000',
        ]);

        $event = EventCreator::applyUpdate($event, $user, $validated);

        return response()->json(['event' => $event]);
    }

    /**
     * DELETE /api/groups/{slug}/events/{id}
     */
    public function destroy(Request $request, string $slug, int $id)
    {
        $group = $request->attributes->get('group');

        $event = Event::where('id', $id)->where('group_id', $group->id)->first();

        if (! $event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();
        if ($event->created_by !== $user->id && ! $group->isAdminOrOwner($user->id) && ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'You do not have permission to delete this event.'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }
}
