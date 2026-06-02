<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the members of a group you belong to, with their roles (owner / admin / member).')]
class ListGroupMembersTool extends Tool
{
    protected string $name = 'list_group_members';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'required|string',
        ]);

        $group = Group::where('slug', $validated['group'])->first();
        if (! $group) {
            return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
        }

        if (! $user->isSuperAdmin() && $group->getMemberRole($user->id) === null) {
            return Response::error('You are not a member of that group.');
        }

        $members = $group->members()
            ->withPivot('role', 'joined_at')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role,
                'joined_at' => $u->pivot->joined_at,
            ]);

        return Response::json([
            'group' => $group->slug,
            'count' => $members->count(),
            'members' => $members,
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
        ];
    }
}
