<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Set the user\'s active (default) group. You must be a member of the group.')]
class SetActiveGroupTool extends Tool
{
    protected string $name = 'set_active_group';

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

        $user->update(['active_group_id' => $group->id]);

        return Response::json([
            'active_group' => $group->slug,
            'name' => $group->name,
            'message' => 'Active group updated.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('The slug of the group to make active (from list_groups).')
                ->required(),
        ];
    }
}
