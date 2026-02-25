<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8|confirmed',
            'referral_code' => 'nullable|string',
            'invite_code'   => 'nullable|string',
        ]);

        // Validate referral code if provided
        if ($request->filled('referral_code')) {
            $referralCode = ReferralCode::where('code', $request->referral_code)->first();

            if (!$referralCode || !$referralCode->isValid()) {
                throw ValidationException::withMessages([
                    'referral_code' => ['Invalid or expired referral code.'],
                ]);
            }
        }

        // Validate invite code if provided (look up group before creating user)
        $inviteGroup = null;
        $invite = null;
        if ($request->filled('invite_code')) {
            $invite = GroupInvite::where('code', $request->invite_code)->first();

            if (!$invite || !$invite->isValid()) {
                throw ValidationException::withMessages([
                    'invite_code' => ['Invalid or expired invite code.'],
                ]);
            }

            $inviteGroup = Group::find($invite->group_id);
        }

        // Create user
        $user = User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => $request->password,
            'platform_role' => 'user',
        ]);

        // Increment referral code usage if used
        if (!empty($referralCode)) {
            $referralCode->increment('current_uses');
        }

        // Auto-join group if invite code was provided
        if ($inviteGroup && $invite) {
            GroupMember::create([
                'group_id'  => $inviteGroup->id,
                'user_id'   => $user->id,
                'role'      => 'member',
                'joined_at' => now(),
            ]);

            $invite->increment('current_uses');

            $user->update(['active_group_id' => $inviteGroup->id]);
        }

        // Auto-join the demo group so new users always have something to explore
        $demoGroup = Group::where('slug', 'demo')->first();
        if ($demoGroup) {
            $alreadyMember = GroupMember::where('group_id', $demoGroup->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$alreadyMember) {
                GroupMember::create([
                    'group_id'  => $demoGroup->id,
                    'user_id'   => $user->id,
                    'role'      => 'member',
                    'joined_at' => now(),
                ]);

                // Set demo as active only if no other group was joined via invite
                $user->refresh();
                if (!$user->active_group_id) {
                    $user->update(['active_group_id' => $demoGroup->id]);
                }
            }
        }

        $user->load(['groups' => fn($q) => $q->withPivot('role')]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        // Load groups for the client to determine redirect
        $user->load(['groups' => fn($q) => $q->withPivot('role')]);

        // Ensure active_group_id is set if user has groups but no active group
        if (!$user->active_group_id && $user->groups->isNotEmpty()) {
            $firstGroup = $user->groups->first();
            $user->update(['active_group_id' => $firstGroup->id]);
            $user->active_group_id = $firstGroup->id;
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['groups' => fn($q) => $q->withPivot('role')]);

        return response()->json(['user' => $user]);
    }

    /**
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name'       => 'sometimes|string|max:255',
            'dob'        => 'sometimes|nullable|date',
            'avatar_url' => 'sometimes|nullable|string|max:500',
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'dob', 'avatar_url']));

        $user->fresh()->load(['groups' => fn($q) => $q->withPivot('role')]);

        return response()->json(['user' => $user->fresh()->load(['groups' => fn($q) => $q->withPivot('role')])]);
    }

    /**
     * PUT /api/auth/active-group
     * Switch the authenticated user's active group.
     */
    public function setActiveGroup(Request $request)
    {
        $request->validate([
            'group_id' => 'required|integer',
        ]);

        $user = $request->user();

        // Verify the user is actually a member of this group
        $isMember = GroupMember::where('group_id', $request->group_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'You are not a member of this group.'], 403);
        }

        $user->update(['active_group_id' => $request->group_id]);

        $user->load(['groups' => fn($q) => $q->withPivot('role')]);

        return response()->json(['user' => $user]);
    }
}
