<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Card 0012: a group's timeline downloads as a zip (timeline.json + photos/)
 * and loads back into a group the user admins, idempotently and safely.
 */
class TimelineExportTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        // A throwaway public dir, so the tests never touch the real uploads.
        $this->public = sys_get_temp_dir().'/tl-export-'.Str::random(8);
        File::ensureDirectoryExists($this->public.'/uploads');
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        parent::tearDown();
    }

    private function groupWith(string $role = 'owner', ?User $user = null, string $name = 'Family'): array
    {
        $user ??= User::factory()->create();
        $group = Group::create(['name' => $name, 'created_by' => $user->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role]);

        return [$user, $group];
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    /** A group with a category, two events and two uploaded photos on disk. */
    private function seededGroup(): array
    {
        [$user, $group] = $this->groupWith('owner');
        $cat = EventCategory::create(['group_id' => $group->id, 'name' => 'Holidays', 'icon' => '🏖', 'color' => '#ff0000']);
        file_put_contents(public_path('uploads/a.png'), $this->png());
        file_put_contents(public_path('uploads/b.png'), $this->png());
        Event::create([
            'group_id' => $group->id, 'title' => 'Beach', 'event_date' => '2019-07-01',
            'category_id' => $cat->id, 'created_by' => $user->id,
            'image_urls' => ['/uploads/a.png', '/uploads/b.png'], 'image_url' => '/uploads/a.png',
        ]);
        Event::create([
            'group_id' => $group->id, 'title' => 'Wedding', 'event_date' => '2020-06-01', 'created_by' => $user->id,
        ]);

        return [$user, $group];
    }

    private function export(User $user, Group $group): TestResponse
    {
        return $this->actingAs($user)->get("/api/groups/{$group->slug}/export");
    }

    /** Write the downloaded zip to disk and return its path. */
    private function saveZip(TestResponse $res): string
    {
        $path = $this->public.'/download-'.Str::random(6).'.zip';
        copy($res->baseResponse->getFile()->getPathname(), $path);

        return $path;
    }

    private function import(User $user, Group $group, string $zipPath): TestResponse
    {
        return $this->actingAs($user)->post("/api/groups/{$group->slug}/import", [
            'file' => new UploadedFile($zipPath, 'timeline.zip', 'application/zip', null, true),
        ], ['Accept' => 'application/json']);
    }

    public function test_an_admin_can_export_a_group_as_a_zip(): void
    {
        [$owner, $group] = $this->seededGroup();
        $admin = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $res = $this->export($admin, $group);

        $res->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->saveZip($res)) === true);
        $json = json_decode($zip->getFromName('timeline.json'), true);
        $this->assertSame(1, $json['version']);
        $this->assertSame('Family', $json['group']['name']);
        $this->assertSame(['Holidays'], array_column($json['categories'], 'name'));
        $this->assertEqualsCanonicalizing(['Beach', 'Wedding'], array_column($json['events'], 'title'));
        $beach = collect($json['events'])->firstWhere('title', 'Beach');
        $this->assertSame(['photos/a.png', 'photos/b.png'], $beach['photos']);
        $this->assertSame($this->png(), $zip->getFromName('photos/a.png'));
        $this->assertNotFalse($zip->getFromName('photos/b.png'));
    }

    public function test_a_member_cannot_export_a_group(): void
    {
        [, $group] = $this->seededGroup();
        $member = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'member']);
        $outsider = User::factory()->create();

        $this->export($member, $group)->assertForbidden();
        $this->export($outsider, $group)->assertForbidden();
        $this->actingAs($member)->post("/api/groups/{$group->slug}/import", [], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_an_export_imports_into_another_group(): void
    {
        [$user, $from] = $this->seededGroup();
        $zip = $this->saveZip($this->export($user, $from));
        [, $to] = $this->groupWith('admin', $user, 'New home');

        $this->import($user, $to, $zip)->assertOk();

        $events = Event::where('group_id', $to->id)->get()->keyBy('title');
        $this->assertEqualsCanonicalizing(['Beach', 'Wedding'], $events->keys()->all());
        $beach = $events['Beach'];
        $this->assertSame('Holidays', $beach->category->name);
        $this->assertSame($to->id, $beach->category->group_id);
        $this->assertSame('2019-07-01', $beach->event_date->toDateString());
        $this->assertCount(2, $beach->image_urls);
        foreach ($beach->image_urls as $url) {
            $this->assertStringStartsWith('/uploads/', $url);
            $this->assertNotContains($url, ['/uploads/a.png', '/uploads/b.png']); // fresh name, not the zip's
            $this->assertSame($this->png(), file_get_contents(public_path(ltrim($url, '/'))));
        }
        $this->assertSame($beach->image_urls[0], $beach->image_url);
    }

    public function test_importing_twice_does_not_duplicate_events(): void
    {
        [$user, $from] = $this->seededGroup();
        $zip = $this->saveZip($this->export($user, $from));
        [, $to] = $this->groupWith('owner', $user, 'New home');

        $this->import($user, $to, $zip)->assertOk();
        Event::where('group_id', $to->id)->where('title', 'Wedding')->update(['title' => 'Edited']);
        $this->import($user, $to, $zip)->assertOk();

        $this->assertSame(2, Event::where('group_id', $to->id)->count());
        $this->assertEqualsCanonicalizing(['Beach', 'Wedding'], Event::where('group_id', $to->id)->pluck('title')->all());
        $this->assertSame(1, EventCategory::where('group_id', $to->id)->count());

        // Importing back into the source group updates its own events too.
        $this->import($user, $from, $zip)->assertOk();
        $this->assertSame(2, Event::where('group_id', $from->id)->count());
    }

    public function test_an_import_cannot_write_outside_uploads(): void
    {
        [$user, $group] = $this->groupWith('owner');
        $path = $this->public.'/evil.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('timeline.json', json_encode(['version' => 1, 'group' => ['name' => 'x'], 'categories' => [], 'events' => [[
            'title' => 'Evil', 'event_date' => '2020-01-01', 'import_hash' => 'h1',
            'photos' => ['photos/../../evil.png', 'photos/shell.php', 'photos/ok.png', '../outside.png'],
        ]]]));
        $zip->addFromString('photos/../../evil.png', $this->png());
        $zip->addFromString('../outside.png', $this->png());
        $zip->addFromString('photos/shell.php', '<?php echo "pwned";');
        $zip->addFromString('photos/sub/nested.png', $this->png());
        $zip->addFromString('photos/ok.png', $this->png());
        $zip->close();
        $before = File::allFiles($this->public);

        $res = $this->import($user, $group, $path)->assertOk();

        $this->assertFileDoesNotExist(dirname($this->public).'/evil.png');
        $this->assertFileDoesNotExist($this->public.'/outside.png');
        $this->assertFileDoesNotExist(dirname($this->public).'/outside.png');
        $new = array_values(array_diff(
            array_map(fn ($f) => $f->getPathname(), File::allFiles($this->public)),
            array_map(fn ($f) => $f->getPathname(), $before),
        ));
        $this->assertCount(1, $new, 'only the one real image is written');
        $this->assertSame(realpath(public_path('uploads')), realpath(dirname($new[0])));
        $this->assertStringEndsWith('.png', $new[0]);
        $this->assertSame($this->png(), file_get_contents($new[0]));
        $this->assertCount(4, $res->json('rejected'));
        $this->assertSame(['/uploads/'.basename($new[0])], Event::sole()->image_urls);
    }

    public function test_an_export_carries_no_personal_account_data(): void
    {
        [$user, $group] = $this->seededGroup();
        $token = $user->createToken('agent', ['events:write'])->plainTextToken;

        $zip = new ZipArchive;
        $zip->open($this->saveZip($this->export($user, $group)));
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= $zip->getNameIndex($i)."\n".$zip->getFromIndex($i);
        }
        $json = $zip->getFromName('timeline.json');

        $this->assertStringNotContainsString($user->email, $all);
        $this->assertStringNotContainsString(explode('|', $token)[1], $all);
        $this->assertStringNotContainsString($user->password, $all);
        $keys = [];
        $walk = function ($a) use (&$walk, &$keys) {
            foreach ($a as $k => $v) {
                $keys[] = $k;
                if (is_array($v)) {
                    $walk($v);
                }
            }
        };
        $walk(json_decode($json, true));
        foreach (['id', 'user_id', 'created_by', 'group_id', 'category_id', 'email', 'token'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "timeline.json carries a '{$forbidden}' key");
        }
    }
}
