<?php

namespace App\Http\Controllers;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * GET /api/admin/referral-codes
     */
    public function referralCodes(Request $request)
    {
        $codes = ReferralCode::with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['referral_codes' => $codes]);
    }

    /**
     * POST /api/admin/referral-codes
     */
    public function createReferralCode(Request $request)
    {
        $request->validate([
            'max_uses' => 'sometimes|integer|min:1|max:1000',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        $code = ReferralCode::create([
            'code' => strtoupper(Str::random(10)),
            'created_by' => $request->user()->id,
            'max_uses' => $request->input('max_uses', 1),
            'expires_at' => $request->expires_at,
        ]);

        return response()->json(['referral_code' => $code], 201);
    }

    /**
     * DELETE /api/admin/referral-codes/{id}
     */
    public function deleteReferralCode(Request $request, int $id)
    {
        $code = ReferralCode::find($id);
        if (!$code) {
            return response()->json(['message' => 'Referral code not found.'], 404);
        }

        $code->delete();

        return response()->json(['message' => 'Referral code deleted.']);
    }

    /**
     * GET /api/admin/users
     */
    public function users(Request $request)
    {
        $users = User::select('id', 'name', 'email', 'platform_role', 'created_at')
            ->withCount('groups')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * PUT /api/admin/users/{id}/role
     */
    public function updateUserRole(Request $request, int $id)
    {
        $request->validate([
            'platform_role' => 'required|in:super_admin,user',
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot change your own role.'], 422);
        }

        $user->update(['platform_role' => $request->platform_role]);

        return response()->json(['user' => $user]);
    }
}
