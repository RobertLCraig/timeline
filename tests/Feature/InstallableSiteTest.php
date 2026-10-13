<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstallableSiteTest extends TestCase
{
    public function test_the_home_page_links_a_complete_web_app_manifest(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<link rel="manifest" href="([^"]+)"/', $html);
        preg_match('/<link rel="manifest" href="([^"]+)"/', $html, $m);

        $path = public_path(ltrim($m[1], '/'));
        $this->assertFileExists($path);
        $manifest = json_decode(file_get_contents($path), true);
        $this->assertIsArray($manifest, 'manifest is not valid JSON');

        foreach (['name', 'short_name', 'start_url', 'theme_color'] as $key) {
            $this->assertNotEmpty($manifest[$key] ?? null, "manifest has no {$key}");
        }
        $this->assertSame('standalone', $manifest['display'] ?? null);

        foreach ([192, 512] as $size) {
            $icon = collect($manifest['icons'] ?? [])->firstWhere('sizes', "{$size}x{$size}");
            $this->assertNotNull($icon, "manifest has no {$size}px icon");
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file);
            [$w, $h] = getimagesize($file);
            $this->assertSame([$size, $size], [$w, $h], "{$icon['src']} is not {$size}x{$size}");
        }
    }

    public function test_a_service_worker_is_served_from_the_root(): void
    {
        // The worker file sits in public/, so it is served at /sw.js and its scope is /.
        $this->assertFileExists(public_path('sw.js'));
        $worker = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("addEventListener('fetch'", $worker, 'Chromium needs a fetch handler to call the site installable');
        $this->assertStringNotContainsString('caches.', $worker, 'the worker must not cache per-user answers');

        // The app registers that root worker, not one under /build/.
        $this->assertStringContainsString(
            "navigator.serviceWorker.register('/sw.js')",
            file_get_contents(resource_path('js/main.jsx')),
        );
    }
}
