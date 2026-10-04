<?php

namespace App\Http\Middleware;

use App\Models\Group;
use Closure;
use Illuminate\Http\Request;

class ResolveGroup
{
    /**
     * Resolve the group from the slug and attach it to the request.
     * Does NOT check membership — use EnsureGroupRole for that.
     */
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->route('slug');
        $group = Group::where('slug', $slug)->first();

        if (! $group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $request->attributes->set('group', $group);

        return $next($request);
    }
}
