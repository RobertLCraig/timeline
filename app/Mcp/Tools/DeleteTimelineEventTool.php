<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a Family Timeline event by event_id. Permanent. You must be the event creator or a group admin/owner.')]
class DeleteTimelineEventTool extends Tool
{
    protected string $name = 'delete_timeline_event';

    public function handle(Request $request): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'event_id' => 'required|integer',
        ]);

        $event = Event::with('group')->find($validated['event_id']);
        if (! $event) {
            return Response::error("Event #{$validated['event_id']} not found.");
        }

        // Same rule as the REST endpoint: creator, group admin/owner, or super admin.
        $canDelete = $event->created_by === $user->id
            || $event->group->isAdminOrOwner($user->id)
            || $user->isSuperAdmin();

        if (! $canDelete) {
            return Response::error('You do not have permission to delete this event.');
        }

        $title = $event->title;
        $event->delete();

        return Response::json([
            'id' => $validated['event_id'],
            'title' => $title,
            'deleted' => true,
            'message' => 'Event deleted.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()
                ->description('The id of the event to delete. This is permanent.')
                ->required(),
        ];
    }
}
