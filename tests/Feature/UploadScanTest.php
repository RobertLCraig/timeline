<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\UploadFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The client-side pre-scan is a filter in front of this, never a replacement
 * for it. An image the browser lets through must still be scanned server-side.
 */
class UploadScanTest extends TestCase
{
    use RefreshDatabase;

    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function enableScanning(): void
    {
        AppSetting::set('nsfw_checks_enabled', '1');
        AppSetting::set('nudity_threshold', '0.6');
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);
    }

    private function upload(): \Illuminate\Testing\TestResponse
    {
        $response = $this->actingAs(User::factory()->create())
            ->post('/api/upload', ['image' => UploadedFile::fake()->image('holiday.jpg')]);

        if ($filename = $response->json('filename')) {
            $this->written[] = public_path('uploads/'.$filename);
        }

        return $response;
    }

    public function test_an_allowed_upload_is_still_scanned_server_side(): void
    {
        $this->enableScanning();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['nudity' => ['none' => 0.99]]),
        ]);

        $this->upload()->assertStatus(201)->assertJson(['flagged' => false]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.sightengine.com'));
    }

    public function test_an_image_over_the_threshold_is_flagged_for_review(): void
    {
        $this->enableScanning();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['nudity' => ['sexual_display' => 0.91]]),
        ]);

        $this->upload()->assertStatus(201)->assertJson(['flagged' => true]);

        $this->assertSame(0.91, (float) UploadFlag::sole()->top_score);
    }
}
