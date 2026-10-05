<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Several photos on one event (card 0009): the list is stored in order,
 * image_url stays the cover (first photo), and legacy single-photo rows read
 * as a one-photo gallery.
 */
class EventPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithGroup(): array
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Family', 'created_by' => $user->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 'owner']);

        return [$user, $group];
    }

    private function postEvent(User $user, Group $group, array $data)
    {
        return $this->actingAs($user)->postJson("/api/groups/{$group->slug}/events", array_merge([
            'title' => 'Wedding',
            'event_date' => '2020-06-01',
        ], $data));
    }

    public function test_an_event_stores_several_photos_in_order(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        $photos = ['/uploads/c.jpg', '/uploads/a.jpg', '/uploads/b.jpg'];

        $res = $this->postEvent($user, $group, ['image_urls' => $photos]);

        $res->assertStatus(201)->assertJsonPath('event.image_urls', $photos);
        $id = $res->json('event.id');
        $this->getJson("/api/groups/{$group->slug}/events/{$id}")
            ->assertJsonPath('event.image_urls', $photos);
        $this->getJson("/api/groups/{$group->slug}/events")
            ->assertJsonPath('data.0.image_urls', $photos);
    }

    public function test_the_first_photo_is_the_cover_image(): void
    {
        [$user, $group] = $this->makeUserWithGroup();

        $id = $this->postEvent($user, $group, ['image_urls' => ['/uploads/1.jpg', '/uploads/2.jpg']])
            ->assertJsonPath('event.image_url', '/uploads/1.jpg')
            ->json('event.id');
        $this->assertSame('/uploads/1.jpg', Event::find($id)->image_url);

        // Reordering the list moves the cover with it.
        $this->actingAs($user)->putJson("/api/groups/{$group->slug}/events/{$id}", [
            'image_urls' => ['/uploads/2.jpg', '/uploads/1.jpg'],
        ])->assertOk()->assertJsonPath('event.image_url', '/uploads/2.jpg');
        $this->assertSame('/uploads/2.jpg', Event::find($id)->image_url);

        // Emptying the list clears the cover.
        $this->actingAs($user)->putJson("/api/groups/{$group->slug}/events/{$id}", [
            'image_urls' => [],
        ])->assertOk();
        $this->assertNull(Event::find($id)->image_url);
        $this->assertSame([], Event::find($id)->image_urls);
    }

    public function test_an_event_with_too_many_photos_is_rejected(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        $tooMany = array_map(fn ($i) => "/uploads/{$i}.jpg", range(1, Event::MAX_PHOTOS + 1));

        $this->postEvent($user, $group, ['image_urls' => $tooMany])
            ->assertStatus(422)->assertJsonValidationErrors('image_urls');
        $this->assertDatabaseMissing('events', ['title' => 'Wedding']);

        // The limit itself is allowed.
        $this->postEvent($user, $group, ['image_urls' => array_slice($tooMany, 0, Event::MAX_PHOTOS)])
            ->assertStatus(201);
    }

    public function test_a_legacy_event_reads_as_a_one_photo_gallery(): void
    {
        [$user, $group] = $this->makeUserWithGroup();
        // A row written before galleries existed: image_url only.
        $event = $user->events()->create([
            'group_id' => $group->id, 'title' => 'Old', 'event_date' => '2001-01-01',
            'visibility' => 'members', 'social_visibility' => 'friends', 'source' => 'web',
            'image_url' => '/uploads/old.jpg',
        ]);
        $bare = $user->events()->create([
            'group_id' => $group->id, 'title' => 'No photo', 'event_date' => '2001-01-02',
            'visibility' => 'members', 'social_visibility' => 'friends', 'source' => 'web',
        ]);

        $this->actingAs($user)->getJson("/api/groups/{$group->slug}/events/{$event->id}")
            ->assertJsonPath('event.image_urls', ['/uploads/old.jpg']);
        $this->actingAs($user)->getJson("/api/groups/{$group->slug}/events/{$bare->id}")
            ->assertJsonPath('event.image_urls', []);
    }
}
