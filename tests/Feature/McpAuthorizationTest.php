<?php

namespace Tests\Feature;

use App\Mcp\Servers\TimelineServer;
use App\Mcp\Tools\CreateGroupInviteTool;
use App\Mcp\Tools\DeleteTimelineEventTool;
use App\Mcp\Tools\ListTimelineEventsTool;
use App\Mcp\Tools\PostTimelineEventTool;
use App\Mcp\Tools\UpdateTimelineEventTool;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\UserGroupVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization tests for the MCP tools — the rule under test is "a user can
 * only edit events/groups they own or administer". These call the tools through
 * laravel/mcp's tester with an authenticated 'api'-guard user.
 */
class McpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function group(User $owner): Group
    {
        $group = Group::create(['name' => 'Family', 'created_by' => $owner->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return $group;
    }

    private function member(Group $group, string $role = 'member'): User
    {
        $u = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $u->id, 'role' => $role]);

        return $u;
    }

    private function event(Group $group, User $creator, array $overrides = []): Event
    {
        return $creator->events()->create(array_merge([
            'group_id' => $group->id,
            'title' => 'An event',
            'event_date' => now()->toDateString(),
            'visibility' => 'members',
            'social_visibility' => 'friends',
            'source' => 'web',
        ], $overrides));
    }

    private function act(User $user, string $tool, array $args)
    {
        return TimelineServer::actingAs($user, 'api')->tool($tool, $args);
    }

    // ── Event edit/delete ────────────────────────────────────────────────

    public function test_member_cannot_update_another_members_event(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $creator = $this->member($group);
        $other = $this->member($group);
        $event = $this->event($group, $creator);

        $this->act($other, UpdateTimelineEventTool::class, ['event_id' => $event->id, 'title' => 'Hijacked'])
            ->assertHasErrors();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'An event']);
    }

    public function test_creator_can_update_own_event(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $creator = $this->member($group);
        $event = $this->event($group, $creator);

        $this->act($creator, UpdateTimelineEventTool::class, ['event_id' => $event->id, 'title' => 'Mine, edited'])
            ->assertHasNoErrors();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Mine, edited']);
    }

    public function test_group_admin_can_update_any_event_in_group(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $admin = $this->member($group, 'admin');
        $creator = $this->member($group);
        $event = $this->event($group, $creator);

        $this->act($admin, UpdateTimelineEventTool::class, ['event_id' => $event->id, 'title' => 'Admin edit'])
            ->assertHasNoErrors();
    }

    public function test_former_member_creator_cannot_update_event(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $creator = $this->member($group);
        $event = $this->event($group, $creator);
        // The creator leaves the group.
        GroupMember::where('group_id', $group->id)->where('user_id', $creator->id)->delete();

        $this->act($creator, UpdateTimelineEventTool::class, ['event_id' => $event->id, 'title' => 'Sneaky'])
            ->assertHasErrors();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'An event']);
    }

    public function test_non_member_cannot_delete_event(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $creator = $this->member($group);
        $event = $this->event($group, $creator);
        $outsider = User::factory()->create();

        $this->act($outsider, DeleteTimelineEventTool::class, ['event_id' => $event->id])
            ->assertHasErrors();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_super_admin_can_delete_any_event(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $creator = $this->member($group);
        $event = $this->event($group, $creator);
        $super = User::factory()->create(['platform_role' => 'super_admin']);

        $this->act($super, DeleteTimelineEventTool::class, ['event_id' => $event->id])
            ->assertHasNoErrors();
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    // ── Posting ──────────────────────────────────────────────────────────

    public function test_non_member_cannot_post_event(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $outsider = User::factory()->create();

        $this->act($outsider, PostTimelineEventTool::class, [
            'group' => $group->slug, 'title' => 'Intruder', 'event_date' => now()->toDateString(),
        ])->assertHasErrors();
        $this->assertDatabaseMissing('events', ['title' => 'Intruder']);
    }

    // ── Group admin actions ──────────────────────────────────────────────

    public function test_plain_member_cannot_create_invite(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $member = $this->member($group);

        $this->act($member, CreateGroupInviteTool::class, ['group' => $group->slug])
            ->assertHasErrors();
    }

    public function test_owner_can_create_invite(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);

        $this->act($owner, CreateGroupInviteTool::class, ['group' => $group->slug])
            ->assertHasNoErrors();
    }

    // ── Social-tier read disclosure ──────────────────────────────────────

    public function test_list_hides_events_above_members_social_tier(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $viewer = $this->member($group);
        // Viewer classifies the group as 'acquaintances' (narrow): should NOT
        // see 'family' / 'close_friends' events.
        UserGroupVisibility::create([
            'user_id' => $viewer->id, 'group_id' => $group->id, 'visibility_tier' => 'acquaintances',
        ]);
        $this->event($group, $owner, ['title' => 'Family secret', 'social_visibility' => 'family']);
        $this->event($group, $owner, ['title' => 'Public picnic', 'social_visibility' => 'public']);

        $this->act($viewer, ListTimelineEventsTool::class, ['group' => $group->slug])
            ->assertSee('Public picnic')
            ->assertDontSee('Family secret');
    }
}
