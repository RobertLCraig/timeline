<?php

namespace App\Mcp\Tools;

use App\Models\EventCategory;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the available event category names that can be passed to post_timeline_event. Requires the categories:read token ability.')]
class ListCategoriesTool extends Tool
{
    protected string $name = 'list_categories';

    public function handle(Request $request): Response
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        if (! $user->tokenCan('categories:read')) {
            return Response::error('This token lacks the "categories:read" ability.');
        }

        $categories = EventCategory::orderBy('name')->get()->map(fn ($c) => [
            'name' => $c->name,
            'icon' => $c->icon,
        ])->values();

        return Response::json(['categories' => $categories]);
    }
}
