<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Join a group using an invite code that was shared with you.')]
class JoinGroupByCodeTool extends Tool
{
    protected string $name = 'join_group_by_code';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'invite_code' => 'required|string',
        ]);

        $invite = GroupInvite::where('code', strtoupper(trim($validated['invite_code'])))->first();
        if (! $invite || ! $invite->isValid()) {
            return Response::error('Invalid or expired invite code.');
        }

        $group = Group::find($invite->group_id);
        if (! $group) {
            return Response::error('The group for this invite no longer exists.');
        }

        if ($group->getMemberRole($user->id) !== null) {
            return Response::error('You are already a member of that group.');
        }

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $invite->increment('current_uses');

        $setActive = false;
        if (! $user->active_group_id) {
            $user->update(['active_group_id' => $group->id]);
            $setActive = true;
        }

        return Response::json([
            'group' => $group->slug,
            'name' => $group->name,
            'role' => 'member',
            'set_active' => $setActive,
            'message' => 'Joined the group.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invite_code' => $schema->string()
                ->description('The invite code shared with you, e.g. "ABCD1234".')
                ->required(),
        ];
    }
}
