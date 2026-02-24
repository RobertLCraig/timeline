<?php

namespace App\Http\Controllers;

use App\Models\CategoryVisibilityDefault;
use App\Models\EventCategory;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\UserGroupVisibility;
use Illuminate\Http\Request;

class VisibilityController extends Controller
{
    // ────────────────────────────────────────────────────────
    // Category Visibility Defaults (per-user)
    // ────────────────────────────────────────────────────────

    /**
     * GET /api/visibility/categories
     * Returns all categories with the current user's default visibility tier.
     */
    public function categoryDefaults(Request $request)
    {
        $user = $request->user();
        $categories = EventCategory::orderBy('name')->get();

        // Load the user's custom defaults
        $userDefaults = CategoryVisibilityDefault::where('user_id', $user->id)
            ->pluck('visibility_tier', 'category_id');

        // System-level defaults for categories without user customisation
        $systemDefaults = [
            'Birth'       => 'family',
            'Wedding'     => 'family',
            'Anniversary' => 'family',
            'Health'      => 'close_friends',
            'Graduation'  => 'friends',
            'Career'      => 'friends',
            'Move'        => 'friends',
            'Travel'      => 'friends',
            'Milestone'   => 'friends',
            'Other'       => 'friends',
        ];

        $result = $categories->map(function ($cat) use ($userDefaults, $systemDefaults) {
            $tier = $userDefaults->get($cat->id)
                ?? $systemDefaults[$cat->name]
                ?? 'friends';

            return [
                'id'              => $cat->id,
                'name'            => $cat->name,
                'icon'            => $cat->icon,
                'color'           => $cat->color,
                'visibility_tier' => $tier,
                'is_customised'   => $userDefaults->has($cat->id),
            ];
        });

        return response()->json(['categories' => $result]);
    }

    /**
     * PUT /api/visibility/categories/{categoryId}
     * Set or update the user's default visibility tier for a category.
     */
    public function updateCategoryDefault(Request $request, int $categoryId)
    {
        $request->validate([
            'visibility_tier' => 'required|in:family,close_friends,friends,acquaintances,public,private',
        ]);

        $category = EventCategory::findOrFail($categoryId);

        CategoryVisibilityDefault::updateOrCreate(
            ['user_id' => $request->user()->id, 'category_id' => $category->id],
            ['visibility_tier' => $request->visibility_tier]
        );

        return response()->json(['message' => 'Category visibility default updated.']);
    }

    // ────────────────────────────────────────────────────────
    // Group Visibility Settings (per-user)
    // ────────────────────────────────────────────────────────

    /**
     * GET /api/visibility/groups
     * Returns all groups the user belongs to with their social tier setting.
     */
    public function groupVisibility(Request $request)
    {
        $user = $request->user();

        $memberships = GroupMember::where('user_id', $user->id)
            ->with('group:id,name,slug')
            ->get();

        $userTiers = UserGroupVisibility::where('user_id', $user->id)
            ->pluck('visibility_tier', 'group_id');

        $result = $memberships->map(function ($m) use ($userTiers) {
            return [
                'group_id'        => $m->group_id,
                'group_name'      => $m->group->name ?? '',
                'group_slug'      => $m->group->slug ?? '',
                'role'            => $m->role,
                'visibility_tier' => $userTiers->get($m->group_id, 'friends'),
                'is_customised'   => $userTiers->has($m->group_id),
            ];
        });

        return response()->json(['groups' => $result]);
    }

    /**
     * PUT /api/visibility/groups/{groupId}
     * Set or update the social tier for one of the user's groups.
     */
    public function updateGroupVisibility(Request $request, int $groupId)
    {
        $request->validate([
            'visibility_tier' => 'required|in:family,close_friends,friends,acquaintances',
        ]);

        // Must be a member of this group
        $isMember = GroupMember::where('user_id', $request->user()->id)
            ->where('group_id', $groupId)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'You are not a member of this group.'], 403);
        }

        UserGroupVisibility::updateOrCreate(
            ['user_id' => $request->user()->id, 'group_id' => $groupId],
            ['visibility_tier' => $request->visibility_tier]
        );

        return response()->json(['message' => 'Group visibility tier updated.']);
    }

    /**
     * GET /api/visibility/tiers
     * Returns the tier list for display in UI dropdowns.
     */
    public function tiers()
    {
        return response()->json([
            'tiers' => [
                ['value' => 'family',       'label' => 'Family',        'description' => 'Only family members'],
                ['value' => 'close_friends', 'label' => 'Close Friends', 'description' => 'Close friends and family'],
                ['value' => 'friends',      'label' => 'Friends',       'description' => 'Friends, close friends & family'],
                ['value' => 'acquaintances', 'label' => 'Acquaintances', 'description' => 'All members'],
                ['value' => 'public',       'label' => 'Public',        'description' => 'Anyone, including non-members'],
                ['value' => 'private',      'label' => 'Private',       'description' => 'Only you'],
            ],
        ]);
    }
}
