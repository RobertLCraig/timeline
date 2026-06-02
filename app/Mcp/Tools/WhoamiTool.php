<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Identify the signed-in user: name, email, their groups with roles, and their active (default) group. Call this first to orient yourself.')]
class WhoamiTool extends Tool
{
    protected string $name = 'whoami';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $groups = $user->groups()->get()->map(fn ($g) => [
            'slug' => $g->slug,
            'name' => $g->name,
            'role' => $g->pivot->role,
        ])->values();

        $activeSlug = $user->activeGroup?->slug;

        return Response::json([
            'name' => $user->name,
            'email' => $user->email,
            'active_group' => $activeSlug,
            'groups' => $groups,
        ]);
    }
}
