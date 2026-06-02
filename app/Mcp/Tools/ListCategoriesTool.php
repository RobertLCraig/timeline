<?php

namespace App\Mcp\Tools;

use App\Models\EventCategory;
use App\Models\Group;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List event category names. Pass a group slug to also include that group\'s own categories; otherwise only the global categories are returned.')]
class ListCategoriesTool extends Tool
{
    protected string $name = 'list_categories';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'sometimes|nullable|string',
        ]);

        $groupId = null;
        if (! empty($validated['group'])) {
            $group = Group::where('slug', $validated['group'])->first();
            if (! $group) {
                return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
            }
            if (! $user->isSuperAdmin() && $group->getMemberRole($user->id) === null) {
                return Response::error('You are not a member of that group.');
            }
            $groupId = $group->id;
        }

        $categories = EventCategory::forGroup($groupId)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'icon' => $c->icon,
                'scope' => $c->group_id ? 'group' : 'global',
            ])->values();

        return Response::json(['categories' => $categories]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('Optional group slug. Include it to also list that group\'s own categories.'),
        ];
    }
}
