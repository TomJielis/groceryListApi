<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $phoneToken = $user->createToken('phone')->plainTextToken;
        $user->createToken('laptop');

        $this->withToken($phoneToken)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertSame(['laptop'], $user->tokens()->pluck('name')->all());

        // The guard caches the user between requests in a test; reset it so the token is checked again
        $this->app['auth']->forgetGuards();

        $this->withToken($phoneToken)
            ->postJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_requires_a_token(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
