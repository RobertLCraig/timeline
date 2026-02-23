<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Group;

class EnsureGroupRole
{
    /**
     * Check that the authenticated user has one of the specified roles in the group.
     * Usage in routes: ->middleware('group.role:owner,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $slug = $request->route('slug');
        $group = Group::where('slug', $slug)->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        // Super admins bypass group role checks
        if ($user->isSuperAdmin()) {
            $request->attributes->set('group', $group);
            return $next($request);
        }

        $memberRole = $group->getMemberRole($user->id);
        if (!$memberRole || !in_array($memberRole, $roles)) {
            return response()->json(['message' => 'Insufficient group permissions.'], 403);
        }

        $request->attributes->set('group', $group);
        return $next($request);
    }
}
