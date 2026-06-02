<?php

namespace App\Mcp\Tools;

use App\Models\EventCategory;
use App\Models\Group;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create an event category for a specific group (only that group can use it). If a usable category with the same name already exists (a global one or one of the group\'s), it is returned instead.')]
class CreateCategoryTool extends Tool
{
    protected string $name = 'create_category';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'required|string',
            'name' => 'required|string|max:50',
            'icon' => 'sometimes|nullable|string|max:16',
            'color' => 'sometimes|nullable|string|max:9',
        ]);

        $group = Group::where('slug', $validated['group'])->first();
        if (! $group) {
            return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
        }

        if (! $user->isSuperAdmin() && $group->getMemberRole($user->id) === null) {
            return Response::error('You must be a member of the group to add a category to it.');
        }

        $name = trim($validated['name']);

        // Idempotent against the categories this group can already use (global
        // or its own) so we don't duplicate an existing one.
        $existing = EventCategory::forGroup($group->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) {
            return Response::json([
                'id' => $existing->id,
                'name' => $existing->name,
                'scope' => $existing->group_id ? 'group' : 'global',
                'created' => false,
                'message' => 'A usable category with this name already exists.',
            ]);
        }

        $attributes = ['name' => $name, 'group_id' => $group->id];
        if (! empty($validated['icon'])) {
            $attributes['icon'] = $validated['icon'];
        }
        if (! empty($validated['color'])) {
            $attributes['color'] = $validated['color'];
        }

        $category = EventCategory::create($attributes);
        $category->refresh();

        return Response::json([
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'group' => $group->slug,
            'scope' => 'group',
            'created' => true,
            'message' => "Category created for {$group->slug}.",
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('The group slug this category is for (from list_groups). Only that group can use it.')
                ->required(),
            'name' => $schema->string()
                ->description('Category name, e.g. "Pets" (max 50 chars).')
                ->required(),
            'icon' => $schema->string()
                ->description('Optional emoji icon, e.g. "🐾".'),
            'color' => $schema->string()
                ->description('Optional hex colour, e.g. "#f59e0b".'),
        ];
    }
}
