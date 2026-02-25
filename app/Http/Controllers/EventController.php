<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\CategoryVisibilityDefault;
use App\Models\UserGroupVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        if (!$group) {
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
        if (!$user) {
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
        if ($isMember && !$isAdminOrOwner) {
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
        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $event = Event::where('id', $id)
            ->where('group_id', $group->id)
            ->with(['category', 'creator:id,name,avatar_url'])
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = Auth::guard('sanctum')->user();

        if (!$event->isVisibleTo($user)) {
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

        $request->validate([
            'title'                  => 'required|string|max:200',
            'description'            => 'nullable|string|max:5000',
            'event_date'             => 'required|date|before_or_equal:' . now()->addYear()->toDateString(),
            'category_id'            => 'nullable|integer|exists:event_categories,id',
            'visibility'             => 'sometimes|in:public,members,private',
            'social_visibility'      => 'sometimes|nullable|in:family,close_friends,friends,acquaintances,public,private',
            'visibility_is_override' => 'sometimes|boolean',
            'image_url'              => 'nullable|string|max:500',
            'album_url'              => 'nullable|url|max:1000',
        ]);

        // Resolve social_visibility from category default if not overridden
        $socialVisibility = $this->resolveSocialVisibility(
            $request->user(),
            $request->category_id,
            $request->social_visibility,
            (bool) $request->input('visibility_is_override', false)
        );

        $event = Event::create([
            'group_id'               => $group->id,
            'title'                  => $request->title,
            'description'            => $request->description,
            'event_date'             => $request->event_date,
            'category_id'            => $request->category_id,
            'created_by'             => $request->user()->id,
            'visibility'             => $request->input('visibility', 'members'),
            'social_visibility'      => $socialVisibility,
            'visibility_is_override' => $request->input('visibility_is_override', false),
            'image_url'              => $request->image_url,
            'album_url'              => $request->album_url,
        ]);

        $event->load(['category', 'creator:id,name,avatar_url']);

        return response()->json(['event' => $event], 201);
    }

    /**
     * PUT /api/groups/{slug}/events/{id}
     */
    public function update(Request $request, string $slug, int $id)
    {
        $group = $request->attributes->get('group');

        $event = Event::where('id', $id)->where('group_id', $group->id)->first();

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();
        if ($event->created_by !== $user->id && !$group->isAdminOrOwner($user->id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'You do not have permission to edit this event.'], 403);
        }

        $request->validate([
            'title'                  => 'sometimes|string|max:200',
            'description'            => 'sometimes|nullable|string|max:5000',
            'event_date'             => 'sometimes|date|before_or_equal:' . now()->addYear()->toDateString(),
            'category_id'            => 'sometimes|nullable|integer|exists:event_categories,id',
            'visibility'             => 'sometimes|in:public,members,private',
            'social_visibility'      => 'sometimes|nullable|in:family,close_friends,friends,acquaintances,public,private',
            'visibility_is_override' => 'sometimes|boolean',
            'image_url'              => 'sometimes|nullable|string|max:500',
            'album_url'              => 'sometimes|nullable|url|max:1000',
        ]);

        // Resolve social_visibility if category changed or override toggled
        $isOverride = $request->has('visibility_is_override')
            ? (bool) $request->visibility_is_override
            : $event->visibility_is_override;

        $categoryId = $request->has('category_id') ? $request->category_id : $event->category_id;

        $socialVisibility = $this->resolveSocialVisibility(
            $user,
            $categoryId,
            $request->social_visibility,
            $isOverride
        ) ?? $event->social_visibility;

        $event->update(array_merge(
            $request->only([
                'title', 'description', 'event_date', 'category_id',
                'visibility', 'image_url', 'album_url',
            ]),
            [
                'social_visibility'      => $socialVisibility,
                'visibility_is_override' => $isOverride,
            ]
        ));

        $event->load(['category', 'creator:id,name,avatar_url']);

        return response()->json(['event' => $event]);
    }

    /**
     * DELETE /api/groups/{slug}/events/{id}
     */
    public function destroy(Request $request, string $slug, int $id)
    {
        $group = $request->attributes->get('group');

        $event = Event::where('id', $id)->where('group_id', $group->id)->first();

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();
        if ($event->created_by !== $user->id && !$group->isAdminOrOwner($user->id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'You do not have permission to delete this event.'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }

    /**
     * Resolve the social_visibility value.
     * If override is true and a value is provided, use that.
     * Otherwise, look up the user's category default (falls back to 'friends').
     */
    private function resolveSocialVisibility($user, ?int $categoryId, ?string $providedValue, bool $isOverride): string
    {
        if ($isOverride && $providedValue) {
            return $providedValue;
        }

        if ($categoryId) {
            $default = CategoryVisibilityDefault::where('user_id', $user->id)
                ->where('category_id', $categoryId)
                ->first();

            if ($default) {
                return $default->visibility_tier;
            }
        }

        // Final fallback
        return $providedValue ?? 'friends';
    }
}
