<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ReferralCode;
use App\Models\UploadFlag;
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

        AuditLog::record($request->user(), 'referral_code.deleted', $code, ['code' => $code->code]);

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

        $previousRole = $user->platform_role;
        $user->update(['platform_role' => $request->platform_role]);

        AuditLog::record($request->user(), 'user.role_changed', $user, [
            'from' => $previousRole,
            'to'   => $request->platform_role,
        ]);

        return response()->json(['user' => $user]);
    }

    // ── NSFW / Content Moderation ────────────────────────────────────────────

    /**
     * GET /api/admin/settings
     * Returns all admin-configurable platform settings.
     */
    public function getSettings()
    {
        return response()->json([
            'settings' => [
                'nsfw_checks_enabled' => AppSetting::get('nsfw_checks_enabled', '0'),
                'nudity_threshold'    => AppSetting::get('nudity_threshold', '0.6'),
            ],
        ]);
    }

    /**
     * PUT /api/admin/settings
     * Updates one or more platform settings. Only known keys are accepted.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'nsfw_checks_enabled' => 'sometimes|in:0,1',
            'nudity_threshold'    => 'sometimes|numeric|min:0|max:1',
        ]);

        $allowed = ['nsfw_checks_enabled', 'nudity_threshold'];
        foreach ($allowed as $key) {
            if ($request->has($key)) {
                AppSetting::set($key, (string) $request->input($key));
            }
        }

        AuditLog::record($request->user(), 'admin.settings_updated', null, $request->only($allowed));

        return response()->json([
            'settings' => [
                'nsfw_checks_enabled' => AppSetting::get('nsfw_checks_enabled'),
                'nudity_threshold'    => AppSetting::get('nudity_threshold'),
            ],
        ]);
    }

    /**
     * GET /api/admin/upload-flags
     * Paginated list of flagged uploads. Filterable by status via ?status=pending|approved|quarantined
     */
    public function uploadFlags(Request $request)
    {
        $query = UploadFlag::with(['uploader:id,name,email', 'reviewer:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $flags = $query->paginate(20);

        return response()->json([
            'flags' => $flags->items(),
            'meta'  => [
                'total'        => $flags->total(),
                'current_page' => $flags->currentPage(),
                'last_page'    => $flags->lastPage(),
            ],
        ]);
    }

    /**
     * PUT /api/admin/upload-flags/{id}
     * Approve or quarantine a flagged upload.
     */
    public function reviewFlag(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:approved,quarantined',
        ]);

        $flag = UploadFlag::find($id);
        if (!$flag) {
            return response()->json(['message' => 'Flag not found.'], 404);
        }

        $flag->update([
            'status'      => $request->status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::record($request->user(), 'upload_flag.' . $request->status, $flag, [
            'flag_id' => $flag->id,
            'url'     => $flag->url,
        ]);

        return response()->json(['flag' => $flag->fresh(['uploader:id,name,email', 'reviewer:id,name'])]);
    }
}
