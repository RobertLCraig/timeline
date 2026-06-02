<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use App\Models\GroupInvite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a shareable invite code for a group. Only a group owner or admin can do this. Share the code so others can join the group.')]
class CreateGroupInviteTool extends Tool
{
    protected string $name = 'create_group_invite';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'required|string',
            'max_uses' => 'sometimes|integer|min:1|max:100',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        $group = Group::where('slug', $validated['group'])->first();
        if (! $group) {
            return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
        }

        // Same rule as the REST group.role:owner,admin middleware.
        if (! $user->isSuperAdmin() && ! $group->isAdminOrOwner($user->id)) {
            return Response::error('Only a group owner or admin can create invites.');
        }

        $invite = GroupInvite::create([
            'group_id' => $group->id,
            'code' => strtoupper(Str::random(8)),
            'created_by' => $user->id,
            'max_uses' => $validated['max_uses'] ?? 1,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return Response::json([
            'group' => $group->slug,
            'code' => $invite->code,
            'max_uses' => $invite->max_uses,
            'expires_at' => $invite->expires_at,
            'message' => 'Invite created. Share this code so others can join the group.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('The group slug (from list_groups).')
                ->required(),
            'max_uses' => $schema->integer()
                ->description('How many times the code may be used (1-100, default 1).'),
            'expires_at' => $schema->string()
                ->description('Optional expiry as an ISO 8601 datetime in the future, e.g. "2026-12-31T23:59:59Z".'),
        ];
    }
}
