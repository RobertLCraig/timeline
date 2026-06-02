<?php

namespace App\Mcp\Tools;

use App\Models\Group;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the active and used invite codes for a group. Only a group owner or admin can view these.')]
class ListGroupInvitesTool extends Tool
{
    protected string $name = 'list_group_invites';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'group' => 'required|string',
        ]);

        $group = Group::where('slug', $validated['group'])->first();
        if (! $group) {
            return Response::error("Group \"{$validated['group']}\" not found. Use list_groups to see valid slugs.");
        }

        if (! $user->isSuperAdmin() && ! $group->isAdminOrOwner($user->id)) {
            return Response::error('Only a group owner or admin can view invites.');
        }

        $invites = $group->invites()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($i) => [
                'code' => $i->code,
                'uses' => $i->current_uses.'/'.$i->max_uses,
                'expires_at' => $i->expires_at,
                'valid' => $i->isValid(),
            ]);

        return Response::json([
            'group' => $group->slug,
            'count' => $invites->count(),
            'invites' => $invites,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('The group slug (from list_groups).')
                ->required(),
        ];
    }
}
