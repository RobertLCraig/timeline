<?php

namespace Tests\Feature;

use App\Mcp\Servers\TimelineServer;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\PostTimelineEventTool;
use App\Models\EventCategory;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryScopeTest extends TestCase
{
    use RefreshDatabase;

    private function ownedGroup(): array
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Fam', 'created_by' => $owner->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$owner, $group];
    }

    private function act(User $user, string $tool, array $args)
    {
        return TimelineServer::actingAs($user, 'api')->tool($tool, $args);
    }

    public function test_created_category_is_scoped_to_its_group(): void
    {
        [$owner, $group] = $this->ownedGroup();

        $this->act($owner, CreateCategoryTool::class, ['group' => $group->slug, 'name' => 'Pets'])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_categories', ['name' => 'Pets', 'group_id' => $group->id]);
    }

    public function test_other_group_cannot_see_or_use_a_groups_category(): void
    {
        [$ownerA, $groupA] = $this->ownedGroup();
        EventCategory::create(['name' => 'Pets', 'group_id' => $groupA->id]);

        [$ownerB, $groupB] = $this->ownedGroup();

        // Not listed for group B.
        $this->act($ownerB, ListCategoriesTool::class, ['group' => $groupB->slug])
            ->assertDontSee('Pets');

        // Cannot post in group B with group A's category name.
        $this->act($ownerB, PostTimelineEventTool::class, [
            'group' => $groupB->slug, 'title' => 'x', 'event_date' => now()->toDateString(), 'category' => 'Pets',
        ])->assertHasErrors();
    }

    public function test_group_can_use_its_own_and_global_categories(): void
    {
        [$owner, $group] = $this->ownedGroup();
        $global = EventCategory::create(['name' => 'Travel']); // group_id null = global
        $own = EventCategory::create(['name' => 'Pets', 'group_id' => $group->id]);

        $this->act($owner, PostTimelineEventTool::class, [
            'group' => $group->slug, 'title' => 'Trip', 'event_date' => now()->toDateString(), 'category' => 'Travel',
        ])->assertHasNoErrors();
        $this->assertDatabaseHas('events', ['title' => 'Trip', 'category_id' => $global->id]);

        $this->act($owner, PostTimelineEventTool::class, [
            'group' => $group->slug, 'title' => 'Puppy', 'event_date' => now()->toDateString(), 'category' => 'Pets',
        ])->assertHasNoErrors();
        $this->assertDatabaseHas('events', ['title' => 'Puppy', 'category_id' => $own->id]);
    }

    public function test_rest_rejects_category_id_from_another_group(): void
    {
        [$ownerA, $groupA] = $this->ownedGroup();
        $catA = EventCategory::create(['name' => 'Pets', 'group_id' => $groupA->id]);

        [$ownerB, $groupB] = $this->ownedGroup();
        $token = $ownerB->createToken('t', ['events:write'])->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/groups/{$groupB->slug}/events", [
                'title' => 'CrossGroup',
                'event_date' => now()->toDateString(),
                'category_id' => $catA->id,
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('category_id');
        $this->assertDatabaseMissing('events', ['title' => 'CrossGroup']);
    }

    public function test_create_category_is_idempotent_against_global(): void
    {
        [$owner, $group] = $this->ownedGroup();
        $global = EventCategory::create(['name' => 'Travel']); // global

        // Creating "Travel" for the group should return the global one, not dupe.
        $this->act($owner, CreateCategoryTool::class, ['group' => $group->slug, 'name' => 'travel'])
            ->assertHasNoErrors()
            ->assertSee('global');

        $this->assertSame(1, EventCategory::whereRaw('LOWER(name) = ?', ['travel'])->count());
    }
}
