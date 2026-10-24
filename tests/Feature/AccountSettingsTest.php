<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Changing the name and language, and accepting (new) terms
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_changes_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Oud']);

        // The website and the app send the fields inside `body`
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/update', ['body' => ['name' => 'Nieuw']])
            ->assertOk()
            ->assertJsonPath('name', 'Nieuw');

        $this->assertSame('Nieuw', $user->fresh()->name);
    }

    public function test_update_language_changes_the_language(): void
    {
        $user = User::factory()->create(['language' => 'nl']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/update-language', ['language' => 'en'])
            ->assertOk()
            ->assertJsonPath('language', 'en');

        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_approve_terms_records_the_version_and_time(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['accepted_terms' => false, 'accepted_terms_version' => '1.0'])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/approve-terms', ['acceptedTermsVersion' => config('app.terms_version')])
            ->assertOk();

        $user->refresh();
        $this->assertTrue((bool) $user->accepted_terms);
        $this->assertSame(config('app.terms_version'), $user->accepted_terms_version);
        $this->assertNotNull($user->accepted_terms_at);
    }

    public function test_the_settings_endpoints_need_a_login(): void
    {
        $this->postJson('/api/update', ['body' => ['name' => 'X']])->assertUnauthorized();
        $this->postJson('/api/update-language', ['language' => 'en'])->assertUnauthorized();
        $this->postJson('/api/approve-terms', ['acceptedTermsVersion' => '1.1'])->assertUnauthorized();
    }

    public function test_settings_only_change_the_users_own_account(): void
    {
        $user = User::factory()->create(['name' => 'Ik', 'language' => 'nl']);
        $other = User::factory()->create(['name' => 'Ander', 'language' => 'nl']);

        $this->actingAs($user, 'sanctum')->postJson('/api/update', ['body' => ['name' => 'Nieuw']]);
        $this->actingAs($user, 'sanctum')->postJson('/api/update-language', ['language' => 'en']);

        $this->assertSame('Ander', $other->fresh()->name);
        $this->assertSame('nl', $other->fresh()->language);
    }
}
