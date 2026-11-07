<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The oldest app version that still works and where to update, per platform, for the app's "update required" screen
 */
class AppVersionTest extends TestCase
{
    public function test_answers_without_logging_in_with_the_configured_versions_and_links(): void
    {
        config([
            'app_version.minimum.ios' => '1.2.0',
            'app_version.minimum.android' => '1.1.0',
            'app_version.minimum_build.ios' => 15,
            'app_version.minimum_build.android' => 3,
            'app_version.update_url.ios' => 'https://testflight.apple.com/join/test',
            'app_version.update_url.android' => 'https://example.test/android',
        ]);

        $this->getJson('/api/app-version')->assertOk()->assertExactJson(['data' => [
            'ios' => ['minimum_version' => '1.2.0', 'minimum_build' => 15, 'update_url' => 'https://testflight.apple.com/join/test'],
            'android' => ['minimum_version' => '1.1.0', 'minimum_build' => 3, 'update_url' => 'https://example.test/android'],
        ]]);
    }

    public function test_lets_every_version_through_by_default(): void
    {
        $this->getJson('/api/app-version')->assertOk()
            ->assertJsonPath('data.ios.minimum_version', '0.0.0')
            ->assertJsonPath('data.android.minimum_version', '0.0.0')
            ->assertJsonPath('data.ios.minimum_build', 0)
            ->assertJsonPath('data.android.minimum_build', 0);
    }
}
