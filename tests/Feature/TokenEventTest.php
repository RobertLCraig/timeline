<?php

namespace Tests\Feature;

use App\Models\EventCategory;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenEventTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithGroup(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Family', 'created_by' => $user->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role]);

        return [$user, $group];
    }

    /** Authenticate the next request with a real personal access token. */
    private function actingAsToken(User $user, array $abilities): static
    {
        $plain = $user->createToken('test', $abilities)->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$plain);
    }

    public function test_events_write_token_can_post_event(): void
    {
        [$user, $group] = $this->makeUserWithGroup();

        $res = $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'A new event',
            'event_date' => now()->toDateString(),
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => 'A new event',
            'group_id' => $group->id,
            'created_by' => $user->id,
            'source' => 'api',
        ]);
    }

    public function test_read_only_token_cannot_post_event(): void
    {
        [$user, $group] = $this->makeUserWithGroup();

        $res = $this->actingAsToken($user, ['events:read'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Should fail',
            'event_date' => now()->toDateString(),
        ]);

        $res->assertStatus(403);
        $this->assertDatabaseMissing('events', ['title' => 'Should fail']);
    }

    public function test_category_can_be_given_by_name(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        $category = EventCategory::create(['name' => 'Travel']);

        $res = $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Trip',
            'event_date' => now()->toDateString(),
            'category' => 'travel', // case-insensitive
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => 'Trip',
            'category_id' => $category->id,
        ]);
    }

    public function test_unknown_category_name_is_rejected(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        EventCategory::create(['name' => 'Travel']);

        $res = $this->actingAsToken($user, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Trip',
            'event_date' => now()->toDateString(),
            'category' => 'Nonsense',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('category');
    }

    public function test_non_member_with_write_token_cannot_post(): void
    {
        [, $group] = $this->makeUserWithGroup();
        $outsider = User::factory()->create();

        $res = $this->actingAsToken($outsider, ['events:write'])->postJson("/api/groups/{$group->slug}/events", [
            'title' => 'Intruder',
            'event_date' => now()->toDateString(),
        ]);

        $res->assertStatus(403);
        $this->assertDatabaseMissing('events', ['title' => 'Intruder']);
    }

    public function test_session_user_can_create_and_revoke_token(): void
    {
        $user = User::factory()->create();

        // A full-ability session token stands in for the SPA cookie session.
        $create = $this->actingAsToken($user, ['*'])->postJson('/api/auth/tokens', [
            'name' => 'Claude',
            'abilities' => ['events:write', 'groups:read'],
        ]);

        $create->assertStatus(201);
        $create->assertJsonStructure(['token', 'id', 'abilities', 'expires_at']);
        $tokenId = $create->json('id');

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'name' => 'Claude',
            'tokenable_id' => $user->id,
        ]);

        // Revoke it.
        $del = $this->actingAsToken($user, ['*'])->deleteJson("/api/auth/tokens/{$tokenId}");
        $del->assertStatus(200);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_agent_token_cannot_manage_tokens(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAsToken($user, ['events:write'])->postJson('/api/auth/tokens', [
            'name' => 'Sneaky',
        ]);

        $res->assertStatus(403);
    }

    public function test_events_write_token_can_update_event(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        $category = EventCategory::create(['name' => 'Travel']);
        $event = $user->events()->create([
            'group_id' => $group->id,
            'title' => 'Original',
            'event_date' => now()->toDateString(),
            'visibility' => 'members',
            'social_visibility' => 'friends',
            'source' => 'web',
        ]);

        $res = $this->actingAsToken($user, ['events:write'])->putJson("/api/groups/{$group->slug}/events/{$event->id}", [
            'title' => 'Renamed',
            'category' => 'travel', // by name
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Renamed',
            'category_id' => $category->id,
            'source' => 'web', // unchanged on edit
        ]);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        [$owner, $group] = $this->makeUserWithGroup();
        $event = $owner->events()->create([
            'group_id' => $group->id,
            'title' => 'Owned',
            'event_date' => now()->toDateString(),
            'visibility' => 'members',
            'social_visibility' => 'friends',
            'source' => 'web',
        ]);
        // A different member who is not the creator nor an admin.
        $member = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'member']);

        $res = $this->actingAsToken($member, ['events:write'])->putJson("/api/groups/{$group->slug}/events/{$event->id}", [
            'title' => 'Hijacked',
        ]);

        $res->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Owned']);
    }

    public function test_creator_can_delete_event(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        $event = $user->events()->create([
            'group_id' => $group->id,
            'title' => 'Disposable',
            'event_date' => now()->toDateString(),
            'visibility' => 'members',
            'social_visibility' => 'friends',
            'source' => 'web',
        ]);

        $res = $this->actingAsToken($user, ['events:write'])->deleteJson("/api/groups/{$group->slug}/events/{$event->id}");

        $res->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        [$owner, $group] = $this->makeUserWithGroup();
        $event = $owner->events()->create([
            'group_id' => $group->id,
            'title' => 'Protected',
            'event_date' => now()->toDateString(),
            'visibility' => 'members',
            'social_visibility' => 'friends',
            'source' => 'web',
        ]);
        $member = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'member']);

        $res = $this->actingAsToken($member, ['events:write'])->deleteJson("/api/groups/{$group->slug}/events/{$event->id}");

        $res->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
