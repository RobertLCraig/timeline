<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/groups/{slug}/events/{id} (card 0014): one event is shown only to
 * someone the timeline would show it to, and a hidden event answers exactly
 * like a missing one.
 */
class EventShowVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function member(Group $group, string $role = 'member'): User
    {
        $user = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function groupWithOwner(): array
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Family', 'created_by' => $owner->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$owner, $group];
    }

    private function event(Group $group, User $creator, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'group_id' => $group->id,
            'title' => 'Wedding',
            'event_date' => '2020-06-01',
            'created_by' => $creator->id,
            'visibility' => 'members',
            'social_visibility' => 'friends',
        ], $overrides));
    }

    private function url(Group $group, int $id): string
    {
        return "/api/groups/{$group->slug}/events/{$id}";
    }

    public function test_one_event_above_the_viewers_tier_is_not_found(): void
    {
        [$owner, $group] = $this->groupWithOwner();
        // Default group tier is 'friends', so a 'family' event is above it.
        $member = $this->member($group);
        $familyOnly = $this->event($group, $owner, ['title' => 'Funeral', 'social_visibility' => 'family']);
        $friends = $this->event($group, $owner, ['title' => 'Picnic']);

        $this->actingAs($member)->getJson($this->url($group, $familyOnly->id))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Event not found.']);

        // The rule still lets them see what the timeline shows them.
        $this->actingAs($member)->getJson($this->url($group, $friends->id))
            ->assertOk()
            ->assertJsonPath('event.title', 'Picnic');
    }

    public function test_a_hidden_event_answers_like_a_missing_one(): void
    {
        [$owner, $group] = $this->groupWithOwner();
        $membersOnly = $this->event($group, $owner);
        $privateEvent = $this->event($group, $owner, ['visibility' => 'private', 'social_visibility' => 'private']);
        $familyOnly = $this->event($group, $owner, ['social_visibility' => 'family']);
        $member = $this->member($group);
        $outsider = User::factory()->create();

        $missing = $this->getJson($this->url($group, 999999))->assertNotFound()->json();

        $cases = [
            'guest, members-only event' => [null, $membersOnly->id],
            'outsider, members-only event' => [$outsider, $membersOnly->id],
            'member, someone else\'s private event' => [$member, $privateEvent->id],
            'member, event above their social tier' => [$member, $familyOnly->id],
        ];

        foreach ($cases as $label => [$user, $id]) {
            $res = $user ? $this->actingAs($user)->getJson($this->url($group, $id)) : $this->getJson($this->url($group, $id));

            $this->assertSame(404, $res->status(), $label);
            $this->assertSame($missing, $res->json(), "body: {$label}");
        }
    }
}
