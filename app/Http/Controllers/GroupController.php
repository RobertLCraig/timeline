<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    /**
     * GET /api/groups — list current user's groups
     */
    public function index(Request $request)
    {
        $groups = $request->user()->groups()
            ->withPivot('role')
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return response()->json(['groups' => $groups]);
    }

    /**
     * POST /api/groups — create a new group
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $group = Group::create([
            'name'        => $request->name,
            'description' => $request->description,
            'created_by'  => $request->user()->id,
        ]);

        // Add creator as owner
        GroupMember::create([
            'group_id' => $group->id,
            'user_id'  => $request->user()->id,
            'role'     => 'owner',
        ]);

        // Set as active group if user has no active group
        $user = $request->user();
        if (!$user->active_group_id) {
            $user->update(['active_group_id' => $group->id]);
        }

        $group->load('members');

        return response()->json(['group' => $group], 201);
    }

    /**
     * GET /api/groups/{slug} — group info (public accessible, optional auth)
     */
    public function show(Request $request, string $slug)
    {
        $group = Group::where('slug', $slug)
            ->withCount('members')
            ->withCount('events')
            ->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        // Use Sanctum guard directly to resolve user on public routes
        $user = Auth::guard('sanctum')->user();
        $membership = null;

        if ($user) {
            $member = GroupMember::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->first();
            $membership = $member ? $member->role : null;
        }

        return response()->json([
            'group'      => $group,
            'membership' => $membership,
        ]);
    }

    /**
     * PUT /api/groups/{slug} — update group (admin/owner)
     */
    public function update(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');

        $request->validate([
            'name'            => 'sometimes|string|max:255',
            'description'     => 'sometimes|nullable|string|max:1000',
            'cover_image_url' => 'sometimes|nullable|string|max:500',
        ]);

        $group->update($request->only(['name', 'description', 'cover_image_url']));

        return response()->json(['group' => $group->fresh()]);
    }

    /**
     * DELETE /api/groups/{slug} — delete group (owner only)
     */
    public function destroy(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');

        // Clear active_group_id for all users who had this as active
        \App\Models\User::where('active_group_id', $group->id)
            ->update(['active_group_id' => null]);

        $group->delete();

        return response()->json(['message' => 'Group deleted successfully.']);
    }

    /**
     * POST /api/groups/join-by-code — join a group using only an invite code (no slug needed)
     */
    public function joinByCode(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        $user = $request->user();

        $invite = GroupInvite::where('code', $request->invite_code)->first();

        if (!$invite || !$invite->isValid()) {
            return response()->json(['message' => 'Invalid or expired invite code.'], 422);
        }

        $group = Group::withCount('members')->find($invite->group_id);
        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You are already a member of this group.'], 422);
        }

        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'role'      => 'member',
            'joined_at' => now(),
        ]);

        $invite->increment('current_uses');

        $wasSetActive = false;
        if (!$user->active_group_id) {
            $user->update(['active_group_id' => $group->id]);
            $wasSetActive = true;
        }

        $group->loadCount('members');

        return response()->json([
            'message'    => 'Successfully joined the group.',
            'group'      => $group,
            'set_active' => $wasSetActive,
        ]);
    }

    /**
     * POST /api/groups/{slug}/join — join group via invite code
     */
    public function join(Request $request, string $slug)
    {
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        $group = Group::where('slug', $slug)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        // Check if already a member
        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You are already a member of this group.'], 422);
        }

        // Validate invite code
        $invite = GroupInvite::where('code', $request->invite_code)
            ->where('group_id', $group->id)
            ->first();

        if (!$invite || !$invite->isValid()) {
            return response()->json(['message' => 'Invalid or expired invite code.'], 422);
        }

        // Add member
        GroupMember::create([
            'group_id' => $group->id,
            'user_id'  => $request->user()->id,
            'role'     => 'member',
        ]);

        $invite->increment('current_uses');

        // Set as active group if user has no active group
        $user = $request->user();
        $wasSetActive = false;
        if (!$user->active_group_id) {
            $user->update(['active_group_id' => $group->id]);
            $wasSetActive = true;
        }

        return response()->json([
            'message'    => 'Successfully joined the group.',
            'group'      => $group,
            'set_active' => $wasSetActive,
        ]);
    }

    /**
     * DELETE /api/groups/{slug}/leave — leave a group
     */
    public function leave(Request $request, string $slug)
    {
        $group = Group::where('slug', $slug)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $user = $request->user();

        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this group.'], 422);
        }

        if ($membership->role === 'owner') {
            return response()->json(['message' => 'Group owners cannot leave. Transfer ownership or delete the group.'], 422);
        }

        $membership->delete();

        // Clear active group if this was the user's active group
        if ((int) $user->active_group_id === (int) $group->id) {
            $nextMembership = GroupMember::where('user_id', $user->id)
                ->where('group_id', '!=', $group->id)
                ->first();

            $user->update(['active_group_id' => $nextMembership ? $nextMembership->group_id : null]);
        }

        return response()->json(['message' => 'You have left the group.']);
    }

    /**
     * GET /api/groups/{slug}/members
     */
    public function members(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');

        $members = $group->members()
            ->withPivot('role', 'joined_at')
            ->get()
            ->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'role'       => $user->pivot->role,
                    'joined_at'  => $user->pivot->joined_at,
                ];
            });

        return response()->json(['members' => $members]);
    }

    /**
     * PUT /api/groups/{slug}/members/{userId} — change member role
     */
    public function updateMember(Request $request, string $slug, int $userId)
    {
        $group = $request->attributes->get('group');

        $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        if ($membership->role === 'owner') {
            return response()->json(['message' => 'Cannot change the owner role.'], 422);
        }

        $membership->update(['role' => $request->role]);

        return response()->json(['message' => 'Member role updated.']);
    }

    /**
     * DELETE /api/groups/{slug}/members/{userId} — remove member
     */
    public function removeMember(Request $request, string $slug, int $userId)
    {
        $group = $request->attributes->get('group');

        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        if ($membership->role === 'owner') {
            return response()->json(['message' => 'Cannot remove the group owner.'], 422);
        }

        $membership->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    /**
     * POST /api/groups/{slug}/invites — create group invite code
     */
    public function createInvite(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');

        $request->validate([
            'max_uses'   => 'sometimes|integer|min:1|max:100',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        $invite = GroupInvite::create([
            'group_id'   => $group->id,
            'code'       => strtoupper(Str::random(8)),
            'created_by' => $request->user()->id,
            'max_uses'   => $request->input('max_uses', 1),
            'expires_at' => $request->expires_at,
        ]);

        return response()->json(['invite' => $invite], 201);
    }

    /**
     * GET /api/groups/{slug}/invites — list group invite codes
     */
    public function invites(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');

        $invites = $group->invites()
            ->with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['invites' => $invites]);
    }

    /**
     * DELETE /api/groups/{slug}/invites/{id} — revoke invite
     */
    public function deleteInvite(Request $request, string $slug, int $id)
    {
        $group = $request->attributes->get('group');

        $invite = GroupInvite::where('id', $id)
            ->where('group_id', $group->id)
            ->first();

        if (!$invite) {
            return response()->json(['message' => 'Invite not found.'], 404);
        }

        $invite->delete();

        return response()->json(['message' => 'Invite revoked.']);
    }
}
