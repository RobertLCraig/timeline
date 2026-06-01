<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the groups the authenticated user belongs to, with the slug and role needed to post events. Requires the groups:read token ability.')]
class ListGroupsTool extends Tool
{
    protected string $name = 'list_groups';

    public function handle(Request $request): Response
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        if (! $user->tokenCan('groups:read')) {
            return Response::error('This token lacks the "groups:read" ability.');
        }

        $groups = $user->groups()->get()->map(fn ($g) => [
            'slug' => $g->slug,
            'name' => $g->name,
            'role' => $g->pivot->role,
        ])->values();

        return Response::json([
            'groups' => $groups,
            'count' => $groups->count(),
        ]);
    }
}
