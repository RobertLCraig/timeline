<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser pre-scan reads the admin kill switch from here, so turning
 * moderation off turns the browser scan off too.
 */
class UploadSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checks_are_reported_off_when_the_setting_is_off(): void
    {
        AppSetting::set('nsfw_checks_enabled', '0');

        $this->actingAs(User::factory()->create())
            ->getJson('/api/upload/settings')
            ->assertOk()
            ->assertExactJson(['nsfw_checks_enabled' => false]);
    }

    public function test_checks_are_reported_on_when_the_setting_is_on(): void
    {
        AppSetting::set('nsfw_checks_enabled', '1');

        $this->actingAs(User::factory()->create())
            ->getJson('/api/upload/settings')
            ->assertOk()
            ->assertExactJson(['nsfw_checks_enabled' => true]);
    }
}
