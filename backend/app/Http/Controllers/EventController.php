<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * GET /api/groups/{slug}/events
     * Returns events filtered by visibility based on auth status.
     */
    public function index(Request $request, string $slug)
    {
        $group = Group::where('slug', $slug)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $user = $request->user();
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

        // Visibility filtering
        if (!$user) {
            // Not logged in: only public events
            $query->where('visibility', 'public');
        } elseif ($isAdminOrOwner) {
            // Admin/owner: see everything
        } elseif ($isMember) {
            // Member: public + members + own private
            $query->where(function ($q) use ($user) {
                $q->whereIn('visibility', ['public', 'members'])
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'private')
                            ->where('created_by', $user->id);
                    });
            });
        } else {
            // Logged in but not a member: only public
            $query->where('visibility', 'public');
        }

        // Optional filters
        if ($request->has('category_id')) {
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
        if ($request->has('visibility') && $isAdminOrOwner) {
            $query->where('visibility', $request->visibility);
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

        if (!$event->isVisibleTo($request->user())) {
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'event_date' => 'required|date',
            'category_id' => 'nullable|integer|exists:event_categories,id',
            'visibility' => 'sometimes|in:public,members,private',
            'image_url' => 'nullable|string|max:500',
            'album_url' => 'nullable|url|max:1000',
        ]);

        $event = Event::create([
            'group_id' => $group->id,
            'title' => $request->title,
            'description' => $request->description,
            'event_date' => $request->event_date,
            'category_id' => $request->category_id,
            'created_by' => $request->user()->id,
            'visibility' => $request->input('visibility', 'members'),
            'image_url' => $request->image_url,
            'album_url' => $request->album_url,
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

        $event = Event::where('id', $id)
            ->where('group_id', $group->id)
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        // Only event creator or group admin/owner can edit
        $user = $request->user();
        if ($event->created_by !== $user->id && !$group->isAdminOrOwner($user->id) && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'You do not have permission to edit this event.'], 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'event_date' => 'sometimes|date',
            'category_id' => 'sometimes|nullable|integer|exists:event_categories,id',
            'visibility' => 'sometimes|in:public,members,private',
            'image_url' => 'sometimes|nullable|string|max:500',
            'album_url' => 'sometimes|nullable|url|max:1000',
        ]);

        $event->update($request->only([
            'title',
            'description',
            'event_date',
            'category_id',
            'visibility',
            'image_url',
            'album_url',
        ]));

        $event->load(['category', 'creator:id,name,avatar_url']);

        return response()->json(['event' => $event]);
    }

    /**
     * DELETE /api/groups/{slug}/events/{id}
     */
    public function destroy(Request $request, string $slug, int $id)
    {
        $group = $request->attributes->get('group');

        $event = Event::where('id', $id)
            ->where('group_id', $group->id)
            ->first();

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
}
