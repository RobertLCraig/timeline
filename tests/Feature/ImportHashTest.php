<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bulk photo-import pipeline relies on `import_hash` to be idempotent: a
 * second post with the same hash must UPDATE the existing event, never create a
 * duplicate — even across many re-runs.
 */
class ImportHashTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithGroup(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Family', 'created_by' => $user->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role]);

        return [$user, $group];
    }

    private function actingAsToken(User $user, array $abilities): static
    {
        $plain = $user->createToken('test', $abilities)->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$plain);
    }

    public function test_first_post_with_import_hash_creates(): void
    {
        [$user, $group] = $this->makeUserWithGroup();

        $res = $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Lakeside day',
            'event_date' => '2015-04-20',
            'import_hash' => 'abc123',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('events', ['title' => 'Lakeside day', 'import_hash' => 'abc123']);
    }

    public function test_repeat_import_hash_updates_not_duplicates(): void
    {
        [$user, $group] = $this->makeUserWithGroup();

        $first = $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Original title',
            'event_date' => '2015-04-20',
            'import_hash' => 'dup-key',
        ]);
        $first->assertStatus(201);
        $id = $first->json('event.id');

        // Re-run the import with a corrected title and the SAME hash.
        $second = $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Corrected title',
            'event_date' => '2015-04-20',
            'import_hash' => 'dup-key',
        ]);

        $second->assertStatus(200);                       // updated, not created
        $this->assertSame($id, $second->json('event.id')); // same row
        $this->assertSame(1, \App\Models\Event::where('group_id', $group->id)->count());
        $this->assertDatabaseHas('events', ['id' => $id, 'title' => 'Corrected title']);
    }

    public function test_same_hash_in_different_groups_does_not_collide(): void
    {
        [$user, $groupA] = $this->makeUserWithGroup();
        $groupB = Group::create(['name' => 'Other', 'created_by' => $user->id]);
        GroupMember::create(['group_id' => $groupB->id, 'user_id' => $user->id, 'role' => 'owner']);

        $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$groupA->slug}/events", [
            'title' => 'In A', 'event_date' => '2015-04-20', 'import_hash' => 'shared',
        ])->assertStatus(201);

        // Same hash, different group => a distinct event (composite unique key).
        $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$groupB->slug}/events", [
            'title' => 'In B', 'event_date' => '2015-04-20', 'import_hash' => 'shared',
        ])->assertStatus(201);

        $this->assertSame(1, \App\Models\Event::where('group_id', $groupA->id)->count());
        $this->assertSame(1, \App\Models\Event::where('group_id', $groupB->id)->count());
    }

    public function test_posts_without_import_hash_never_collide(): void
    {
        [$user, $group] = $this->makeUserWithGroup();

        foreach (['One', 'Two', 'Three'] as $title) {
            $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
                'title' => $title, 'event_date' => '2015-04-20',
            ])->assertStatus(201);
        }

        // Three NULL-hash rows coexist (NULLs are distinct in the unique index).
        $this->assertSame(3, \App\Models\Event::where('group_id', $group->id)->count());
    }

    public function test_other_member_cannot_overwrite_via_import_hash(): void
    {
        [$owner, $group] = $this->makeUserWithGroup();
        // Create the owner's imported event directly (one HTTP mutation per test
        // keeps Sanctum auth from leaking across users — see TokenEventTest).
        $owner->events()->create([
            'group_id' => $group->id,
            'title' => 'Owner event',
            'event_date' => '2015-04-20',
            'visibility' => 'members',
            'social_visibility' => 'friends',
            'source' => 'api',
            'import_hash' => 'owned',
        ]);

        $member = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'member']);

        $res = $this->actingAsToken($member, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Hijack', 'event_date' => '2015-04-20', 'import_hash' => 'owned',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('import_hash');
        $this->assertDatabaseHas('events', ['title' => 'Owner event']);
        $this->assertDatabaseMissing('events', ['title' => 'Hijack']);
    }
}
