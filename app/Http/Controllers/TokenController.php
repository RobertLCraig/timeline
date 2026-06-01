<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TokenController extends Controller
{
    /**
     * Abilities a user is allowed to grant to a personal access token.
     * Tokens are intended for AI agents / scripts posting to the timeline,
     * so they can never manage other tokens, the account, or admin settings.
     */
    public const GRANTABLE_ABILITIES = [
        'events:write' => 'Create and edit timeline events',
        'events:read' => 'Read timeline events',
        'groups:read' => 'List groups the user belongs to',
        'categories:read' => 'List event categories',
    ];

    /** Suggested default ability set for an agent token. */
    public const DEFAULT_ABILITIES = ['events:write', 'groups:read', 'categories:read'];

    /** Hard backstop lifetime. Day-to-day hygiene is the 180-day rotation nudge. */
    private const TOKEN_LIFETIME_YEARS = 2;

    /** Tokens older than this (days) get a "consider rotating" badge in the UI. */
    public const ROTATION_NUDGE_DAYS = 180;

    /**
     * GET /api/auth/tokens
     * List the current user's tokens. Never returns the plaintext value.
     */
    public function index(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at,
                'expires_at' => $t->expires_at,
                'created_at' => $t->created_at,
                'stale' => $t->created_at->diffInDays(now()) >= self::ROTATION_NUDGE_DAYS,
            ]);

        return response()->json([
            'tokens' => $tokens,
            'grantable' => self::GRANTABLE_ABILITIES,
            'default_abilities' => self::DEFAULT_ABILITIES,
            'rotation_nudge_days' => self::ROTATION_NUDGE_DAYS,
        ]);
    }

    /**
     * POST /api/auth/tokens
     * Create a token. Returns the plaintext value ONCE — it cannot be retrieved again.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'abilities' => 'sometimes|array',
            'abilities.*' => ['string', Rule::in(array_keys(self::GRANTABLE_ABILITIES))],
        ]);

        $abilities = $validated['abilities'] ?? self::DEFAULT_ABILITIES;
        // Never allow an empty ability set (Sanctum treats [] as "all abilities").
        if (empty($abilities)) {
            $abilities = self::DEFAULT_ABILITIES;
        }

        $expiresAt = now()->addYears(self::TOKEN_LIFETIME_YEARS);

        $newToken = $request->user()->createToken($validated['name'], $abilities, $expiresAt);

        return response()->json([
            'token' => $newToken->plainTextToken, // shown once
            'id' => $newToken->accessToken->id,
            'name' => $newToken->accessToken->name,
            'abilities' => $newToken->accessToken->abilities,
            'expires_at' => $newToken->accessToken->expires_at,
            'message' => 'Copy this token now — it will not be shown again.',
        ], 201);
    }

    /**
     * DELETE /api/auth/tokens/{id}
     * Revoke a token belonging to the current user.
     */
    public function destroy(Request $request, int $id)
    {
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        return response()->json(['message' => 'Token revoked.']);
    }
}
