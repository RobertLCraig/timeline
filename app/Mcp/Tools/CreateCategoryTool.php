<?php

namespace App\Mcp\Tools;

use App\Models\EventCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create an event category if it does not already exist. Categories are shared across all groups. If the name already exists (case-insensitive) the existing one is returned.')]
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
            'name' => 'required|string|max:50',
            'icon' => 'sometimes|nullable|string|max:16',
            'color' => 'sometimes|nullable|string|max:9',
        ]);

        $name = trim($validated['name']);

        $existing = EventCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return Response::json([
                'id' => $existing->id,
                'name' => $existing->name,
                'icon' => $existing->icon,
                'color' => $existing->color,
                'created' => false,
                'message' => 'A category with this name already exists.',
            ]);
        }

        // Only set icon/color when provided so the table defaults (📌 / brand
        // colour) apply — the columns are NOT NULL, so passing null would fail.
        $attributes = ['name' => $name];
        if (! empty($validated['icon'])) {
            $attributes['icon'] = $validated['icon'];
        }
        if (! empty($validated['color'])) {
            $attributes['color'] = $validated['color'];
        }

        $category = EventCategory::create($attributes);
        $category->refresh(); // load DB-applied defaults for icon/color

        return Response::json([
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'created' => true,
            'message' => 'Category created.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Category name, e.g. "Travel" (max 50 chars).')
                ->required(),
            'icon' => $schema->string()
                ->description('Optional emoji icon for the category, e.g. "✈️".'),
            'color' => $schema->string()
                ->description('Optional hex colour, e.g. "#f59e0b".'),
        ];
    }
}
