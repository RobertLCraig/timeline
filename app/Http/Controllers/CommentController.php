<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventComment;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommentController extends Controller
{
    /**
     * GET /api/groups/{slug}/events/{id}/comments[?since=ISO-8601]
     *
     * With `since`, only comments created after that time, so a quiet poll
     * returns an empty list.
     */
    public function index(Request $request, string $slug, int $id)
    {
        $event = $this->visibleEvent($request->user(), $slug, $id);
        if (! $event) {
            return $this->notFound();
        }

        $request->validate(['since' => 'sometimes|date']);

        // ponytail: second-precision `since`; a comment written in the same
        // second as the client's newest one can be missed. Poll by id if that bites.
        $comments = EventComment::where('event_id', $event->id)
            ->when($request->filled('since'), fn ($q) => $q->where('created_at', '>', Carbon::parse($request->input('since'))))
            ->with('user:id,name,avatar_url')
            ->oldest()
            ->oldest('id')
            ->get();

        return response()->json(['comments' => $comments]);
    }

    /**
     * POST /api/groups/{slug}/events/{id}/comments
     */
    public function store(Request $request, string $slug, int $id)
    {
        $event = $this->visibleEvent($request->user(), $slug, $id);
        if (! $event) {
            return $this->notFound();
        }

        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $comment = EventComment::create([
            'event_id' => $event->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ])->load('user:id,name,avatar_url');

        return response()->json(['comment' => $comment], 201);
    }

    /**
     * DELETE /api/groups/{slug}/events/{id}/comments/{commentId}
     *
     * Same rule as editing an event: the author, a group admin/owner, or a
     * super admin.
     */
    public function destroy(Request $request, string $slug, int $id, int $commentId)
    {
        $user = $request->user();
        $event = $this->visibleEvent($user, $slug, $id);
        $comment = $event ? EventComment::where('event_id', $event->id)->find($commentId) : null;
        if (! $comment) {
            return $this->notFound();
        }

        if ($comment->user_id !== $user->id && ! $event->group->isAdminOrOwner($user->id) && ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'You do not have permission to delete this comment.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    /**
     * The event, if this user is a member of its group (or a super admin) and
     * the timeline would show it to them. Null otherwise, and the caller
     * answers exactly as for a missing event.
     */
    private function visibleEvent(User $user, string $slug, int $id): ?Event
    {
        $group = Group::where('slug', $slug)->first();
        if (! $group || ($group->getMemberRole($user->id) === null && ! $user->isSuperAdmin())) {
            return null;
        }

        return Event::visibleIn($group, $user)->whereKey($id)->first();
    }

    private function notFound()
    {
        return response()->json(['message' => 'Event not found.'], 404);
    }
}
