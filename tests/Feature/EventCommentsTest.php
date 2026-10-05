<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventComment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Comments on an event (card 0011): only people who can see the event reach
 * its comments, `since` returns only newer ones, deletes follow the event
 * edit rule, and posting is throttled.
 */
class EventCommentsTest extends TestCase
{
    use RefreshDatabase;

    private function member(Group $group, string $role = 'member'): User
    {
        $user = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function groupWithEvent(array $event = []): array
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Family', 'created_by' => $owner->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $event = Event::create(array_merge([
            'group_id' => $group->id,
            'title' => 'Wedding',
            'event_date' => '2020-06-01',
            'created_by' => $owner->id,
            'visibility' => 'members',
            'social_visibility' => 'friends',
        ], $event));

        return [$owner, $group, $event];
    }

    private function url(Group $group, int $eventId): string
    {
        return "/api/groups/{$group->slug}/events/{$eventId}/comments";
    }

    public function test_a_member_can_comment_on_an_event_they_can_see(): void
    {
        [, $group, $event] = $this->groupWithEvent();
        $member = $this->member($group);

        $this->actingAs($member)->postJson($this->url($group, $event->id), ['body' => 'Uncle Jim took this one.'])
            ->assertStatus(201)
            ->assertJsonPath('comment.body', 'Uncle Jim took this one.')
            ->assertJsonPath('comment.user.name', $member->name);

        $comment = EventComment::sole();
        $this->assertSame($event->id, $comment->event_id);
        $this->assertSame($member->id, $comment->user_id);
        $this->assertNotNull($comment->created_at);

        $this->actingAs($member)->getJson($this->url($group, $event->id))
            ->assertOk()
            ->assertJsonCount(1, 'comments')
            ->assertJsonPath('comments.0.body', 'Uncle Jim took this one.');
    }

    public function test_comments_on_a_hidden_event_are_not_reachable(): void
    {
        [$owner, $group, $membersOnly] = $this->groupWithEvent();
        $privateEvent = Event::create([
            'group_id' => $group->id, 'title' => 'Diary', 'event_date' => '2020-01-01',
            'created_by' => $owner->id, 'visibility' => 'private', 'social_visibility' => 'private',
        ]);
        // Shared with 'family' only; a member whose group tier is the default
        // 'friends' passes the legacy check but not the social tier.
        $familyOnly = Event::create([
            'group_id' => $group->id, 'title' => 'Funeral', 'event_date' => '2021-01-01',
            'created_by' => $owner->id, 'visibility' => 'members', 'social_visibility' => 'family',
        ]);
        $member = $this->member($group);
        $outsider = User::factory()->create();

        $cases = [
            'outsider, members-only event' => [$outsider, $membersOnly->id],
            'member, someone else\'s private event' => [$member, $privateEvent->id],
            'member, event above their social tier' => [$member, $familyOnly->id],
            'member, no such event' => [$member, 999999],
        ];

        foreach ($cases as $label => [$user, $eventId]) {
            $get = $this->actingAs($user)->getJson($this->url($group, $eventId));
            $post = $this->actingAs($user)->postJson($this->url($group, $eventId), ['body' => 'hi']);

            $this->assertSame(404, $get->status(), "GET: {$label}");
            $this->assertSame(404, $post->status(), "POST: {$label}");
            // Same body as a missing event, so the answer does not tell them apart.
            $this->assertSame(['message' => 'Event not found.'], $get->json(), "GET body: {$label}");
            $this->assertSame(['message' => 'Event not found.'], $post->json(), "POST body: {$label}");
        }

        $this->assertSame(0, EventComment::count());
    }

    public function test_comments_since_returns_only_newer_comments(): void
    {
        [$owner, $group, $event] = $this->groupWithEvent();

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->actingAs($owner)->postJson($this->url($group, $event->id), ['body' => 'old'])->assertStatus(201);
        Carbon::setTestNow('2026-10-05 10:00:30');
        $this->actingAs($owner)->postJson($this->url($group, $event->id), ['body' => 'new'])->assertStatus(201);
        Carbon::setTestNow();

        $res = $this->actingAs($owner)->getJson($this->url($group, $event->id).'?since='.urlencode('2026-10-05T10:00:00Z'))
            ->assertOk();
        $this->assertSame(['new'], array_column($res->json('comments'), 'body'));

        // A quiet poll returns an empty list.
        $this->actingAs($owner)->getJson($this->url($group, $event->id).'?since='.urlencode('2026-10-05T10:00:30Z'))
            ->assertOk()->assertJsonCount(0, 'comments');
    }

    public function test_only_the_author_or_an_admin_can_delete_a_comment(): void
    {
        [$owner, $group, $event] = $this->groupWithEvent();
        $author = $this->member($group);
        $other = $this->member($group);
        $admin = $this->member($group, 'admin');

        $post = fn () => $this->actingAs($author)->postJson($this->url($group, $event->id), ['body' => 'mine'])->json('comment.id');

        // Another plain member is refused and the comment stays.
        $id = $post();
        $this->actingAs($other)->deleteJson($this->url($group, $event->id)."/{$id}")->assertStatus(403);
        $this->assertTrue(EventComment::whereKey($id)->exists());

        // The author can delete their own.
        $this->actingAs($author)->deleteJson($this->url($group, $event->id)."/{$id}")->assertOk();
        $this->assertFalse(EventComment::whereKey($id)->exists());

        // A group admin and the owner can delete anyone's.
        foreach ([$admin, $owner] as $mod) {
            $id = $post();
            $this->actingAs($mod)->deleteJson($this->url($group, $event->id)."/{$id}")->assertOk();
            $this->assertFalse(EventComment::whereKey($id)->exists());
        }
    }

    public function test_comment_posting_is_rate_limited(): void
    {
        [, $group, $event] = $this->groupWithEvent();
        $member = $this->member($group);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($member)->postJson($this->url($group, $event->id), ['body' => "c{$i}"])->assertStatus(201);
        }
        $this->actingAs($member)->postJson($this->url($group, $event->id), ['body' => 'one too many'])->assertStatus(429);
        $this->assertSame(10, EventComment::count());

        // Reading is not throttled by the posting limit.
        $this->actingAs($member)->getJson($this->url($group, $event->id))->assertOk();
    }
}
