<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new timeline group. You become its owner. The slug is generated from the name.')]
class CreateGroupTool extends Tool
{
    protected string $name = 'create_group';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
        ]);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Make it the active group if the user doesn't have one yet.
        if (! $user->active_group_id) {
            $user->update(['active_group_id' => $group->id]);
        }

        return Response::json([
            'slug' => $group->slug,
            'name' => $group->name,
            'role' => 'owner',
            'url' => url("/g/{$group->slug}"),
            'message' => 'Group created. You are the owner.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Name of the new group, e.g. "The Craig Family" (max 255 chars).')
                ->required(),
            'description' => $schema->string()
                ->description('Optional short description of the group (max 1000 chars).'),
        ];
    }
}
