<?php

namespace App\Http\Controllers;

use App\Models\EventCategory;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     *
     * Returns the global categories. Pass ?group={slug} to also include that
     * group's own categories — but only if the requester is a member of it.
     */
    public function index(Request $request)
    {
        $groupId = null;

        if ($request->filled('group')) {
            $group = Group::where('slug', $request->input('group'))->first();
            $user = Auth::guard('sanctum')->user();

            if ($group && $user && ($user->isSuperAdmin() || $group->getMemberRole($user->id) !== null)) {
                $groupId = $group->id;
            }
        }

        $categories = EventCategory::forGroup($groupId)->orderBy('name')->get();

        return response()->json(['categories' => $categories]);
    }
}
