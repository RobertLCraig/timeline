<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\ReferralCode;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    // Lock account for 15 minutes after this many consecutive failures
    private const MAX_ATTEMPTS = 10;
    private const LOCKOUT_MINUTES = 15;

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

        // Send email verification notification
        event(new Registered($user));

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

        Auth::login($user);

        return response()->json(['user' => $user], 201);
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

        // Check account lockout before verifying password
        if ($user && $user->locked_until && now()->lt($user->locked_until)) {
            $minutesLeft = (int) ceil(now()->diffInMinutes($user->locked_until));
            throw ValidationException::withMessages([
                'email' => ["Account locked due to too many failed attempts. Try again in {$minutesLeft} minute(s)."],
            ]);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Track failed attempt
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $updates  = ['failed_login_attempts' => $attempts];
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $updates['locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
                }
                $user->update($updates);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Successful login — reset lockout counters
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        // Load groups for the client to determine redirect
        $user->load(['groups' => fn($q) => $q->withPivot('role')]);

        // Ensure active_group_id is set if user has groups but no active group
        if (!$user->active_group_id && $user->groups->isNotEmpty()) {
            $firstGroup = $user->groups->first();
            $user->update(['active_group_id' => $firstGroup->id]);
            $user->active_group_id = $firstGroup->id;
        }

        // If MFA is enabled, store user ID in session and challenge the client
        if ($user->mfa_enabled) {
            $request->session()->put('mfa_user_id', $user->id);
            return response()->json(['mfa_required' => true]);
        }

        // Establish session (SPA cookie auth)
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Always return success to prevent email enumeration attacks
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'              => Hash::make($password),
                    'failed_login_attempts' => 0,
                    'locked_until'          => null,
                ])->save();

                // Invalidate all existing sessions on password reset — user must log in again
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset successfully. Please log in with your new password.']);
    }

    // ── MFA ────────────────────────────────────────────────────────────────

    /**
     * POST /api/auth/mfa/enable
     * Generates a new TOTP secret and returns a QR code SVG for the user to scan.
     * The secret is stored in the session (not saved to DB until confirmed).
     */
    public function mfaEnable(Request $request)
    {
        $user = $request->user();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);

        // Temporarily store secret until the user confirms with a valid code
        $request->session()->put('mfa_pending_secret', $secret);

        $otpUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $qrSvg = (new Writer($renderer))->writeString($otpUrl);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => base64_encode($qrSvg),
        ]);
    }

    /**
     * POST /api/auth/mfa/confirm
     * Saves the pending secret once the user has entered a valid code from their authenticator app.
     */
    public function mfaConfirm(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $secret = $request->session()->get('mfa_pending_secret');
        if (!$secret) {
            return response()->json(['message' => 'No pending MFA setup. Please start setup again.'], 422);
        }

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user = $request->user();
        $user->update([
            'totp_secret' => encrypt($secret),
            'mfa_enabled' => true,
        ]);

        $request->session()->forget('mfa_pending_secret');

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'user'    => $user->fresh()->load(['groups' => fn($q) => $q->withPivot('role')]),
        ]);
    }

    /**
     * POST /api/auth/mfa/disable
     * Disables TOTP MFA after verifying the user's password.
     */
    public function mfaDisable(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.'],
            ]);
        }

        $user->update(['totp_secret' => null, 'mfa_enabled' => false]);

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
            'user'    => $user->fresh()->load(['groups' => fn($q) => $q->withPivot('role')]),
        ]);
    }

    /**
     * POST /api/auth/mfa/verify
     * Completes login when MFA is required — verifies the TOTP code and establishes the session.
     */
    public function mfaVerify(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) {
            return response()->json(['message' => 'No active MFA challenge. Please log in again.'], 422);
        }

        $user = User::find($userId);
        if (!$user || !$user->mfa_enabled || !$user->totp_secret) {
            $request->session()->forget('mfa_user_id');
            return response()->json(['message' => 'MFA state is invalid. Please log in again.'], 422);
        }

        $google2fa = new Google2FA();
        $secret    = decrypt($user->totp_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid authentication code.'], 422);
        }

        $request->session()->forget('mfa_user_id');

        $user->load(['groups' => fn($q) => $q->withPivot('role')]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user]);
    }

    // ── Google OAuth ────────────────────────────────────────────────────────

    /**
     * GET /api/auth/oauth/google/redirect
     * Redirects the browser to Google's OAuth consent screen.
     * The frontend navigates the tab to this URL directly (window.location.href).
     */
    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * GET /api/auth/oauth/google/callback
     * Handles the Google OAuth callback: finds or creates a user, logs them in,
     * and redirects back to the frontend so the SPA can detect the session.
     */
    public function googleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL', '/') . '?oauth_error=1');
        }

        // Find existing user by Google ID, or by matching email
        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Link Google ID if not already linked
            if (!$user->google_id) {
                $user->update([
                    'google_id'         => $googleUser->getId(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }
        } else {
            // Create a new account for this Google user
            $user = User::create([
                'name'              => $googleUser->getName(),
                'email'             => $googleUser->getEmail(),
                'google_id'         => $googleUser->getId(),
                'password'          => Hash::make(Str::random(40)),
                'platform_role'     => 'user',
                'email_verified_at' => now(),
            ]);

            // Auto-join the demo group
            $demoGroup = Group::where('slug', 'demo')->first();
            if ($demoGroup) {
                GroupMember::create([
                    'group_id'  => $demoGroup->id,
                    'user_id'   => $user->id,
                    'role'      => 'member',
                    'joined_at' => now(),
                ]);
                $user->update(['active_group_id' => $demoGroup->id]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(env('FRONTEND_URL', '/'));
    }

    // ── Email Verification ──────────────────────────────────────────────────

    /**
     * GET /api/auth/email/verify/{id}/{hash}
     * Handles the signed verification link from the email.
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(env('FRONTEND_URL', '/') . '/profile?verified=invalid');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(env('FRONTEND_URL', '/') . '/profile?verified=1');
    }

    /**
     * POST /api/auth/email/resend
     * Resends the email verification notification.
     */
    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    // ── GDPR ────────────────────────────────────────────────────────────────

    /**
     * GET /api/me/export
     * Returns a JSON export of all personal data held for the authenticated user.
     * The response has Content-Disposition: attachment so the browser saves the file.
     */
    public function export(Request $request)
    {
        $user = $request->user()->load([
            'groups' => fn($q) => $q->withPivot('role', 'joined_at'),
            'events',
        ]);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'dob'               => $user->dob?->toDateString(),
                'platform_role'     => $user->platform_role,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'mfa_enabled'       => $user->mfa_enabled,
                'created_at'        => $user->created_at->toIso8601String(),
            ],
            'groups' => $user->groups->map(fn($g) => [
                'id'        => $g->id,
                'name'      => $g->name,
                'slug'      => $g->slug,
                'role'      => $g->pivot->role,
                'joined_at' => $g->pivot->joined_at,
            ])->values(),
            'events' => $user->events->map(fn($e) => [
                'id'                => $e->id,
                'group_id'          => $e->group_id,
                'title'             => $e->title,
                'description'       => $e->description,
                'event_date'        => $e->event_date?->toDateString(),
                'category_id'       => $e->category_id,
                'visibility'        => $e->visibility,
                'social_visibility' => $e->social_visibility,
                'created_at'        => $e->created_at->toIso8601String(),
            ])->values(),
        ];

        return response()->json($payload)
            ->header('Content-Disposition', 'attachment; filename="my-data-export.json"')
            ->header('Content-Type', 'application/json');
    }

    /**
     * DELETE /api/me
     * Permanently deletes the authenticated user's account.
     * - Nullifies created_by on their events (preserves group history)
     * - Removes all group memberships
     * - Ends the session
     * Requires password confirmation (skipped for Google-only accounts).
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Password-only accounts must confirm; Google-only accounts (no password) skip this
        if ($user->password && !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.'],
            ]);
        }

        // Preserve group events but remove identity link
        Event::where('created_by', $user->id)->update(['created_by' => null]);

        // Remove all group memberships
        \Illuminate\Support\Facades\DB::table('group_members')
            ->where('user_id', $user->id)
            ->delete();

        // End session before deleting the record
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        return response()->json(['message' => 'Your account has been permanently deleted.']);
    }
}
