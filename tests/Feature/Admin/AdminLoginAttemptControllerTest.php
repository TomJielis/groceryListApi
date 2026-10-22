<?php

namespace Tests\Feature\Admin;

use App\Models\InvalidLoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginAttemptControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function attempt(array $attributes = []): InvalidLoginAttempt
    {
        return InvalidLoginAttempt::create(array_merge([
            'user_id' => null,
            'username' => 'someone@example.com',
            'ip_address' => '203.0.113.5',
            'attempted_at' => now(),
            'blocked_till' => null,
        ], $attributes));
    }

    public function test_only_admins_can_see_it(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/login-attempts')
            ->assertForbidden();
    }

    public function test_lists_attempts_newest_first_with_a_summary(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com']);
        $this->attempt(['user_id' => $victim->id, 'username' => 'victim@example.com', 'attempted_at' => now()->subHour()]);
        $this->attempt(['user_id' => $victim->id, 'username' => 'victim@example.com', 'ip_address' => '198.51.100.7', 'blocked_till' => now()->addMinutes(15)]);
        $this->attempt(['username' => 'nobody@example.com']);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/login-attempts');

        $response->assertOk()
            ->assertJsonPath('summary.attempts', 3)
            ->assertJsonPath('summary.accounts', 2)
            ->assertJsonPath('summary.ip_addresses', 2)
            ->assertJsonPath('summary.lockouts', 1)
            ->assertJsonPath('summary.top_accounts.0.email', 'victim@example.com')
            ->assertJsonPath('summary.top_accounts.0.attempts', 2)
            ->assertJsonPath('summary.top_ip_addresses.0.ip_address', '203.0.113.5')
            ->assertJsonPath('data.2.user.name', $victim->name)
            ->assertJsonPath('data.2.caused_lockout', false);
        $this->assertNull($response->json('data.0.user'));
    }

    public function test_filters_by_period_account_email_and_ip(): void
    {
        $user = User::factory()->create();
        $this->attempt(['attempted_at' => now()->subDays(10)]);
        $this->attempt(['user_id' => $user->id, 'username' => 'known@example.com']);
        $this->attempt(['username' => 'typo@exmaple.com', 'ip_address' => '198.51.100.7']);

        $get = fn (string $query) => $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/login-attempts?' . $query)
            ->assertOk()
            ->json('summary.attempts');

        $this->assertSame(2, $get('period=7d'));
        $this->assertSame(3, $get('period=all'));
        $this->assertSame(1, $get('account=known'));
        $this->assertSame(1, $get('account=unknown'));
        $this->assertSame(1, $get('email=exmaple'));
        $this->assertSame(1, $get('ip=198.51.100.7'));
    }

    public function test_rejects_an_unknown_period(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/login-attempts?period=forever')
            ->assertUnprocessable();
    }

    public function test_a_failed_login_through_the_local_proxy_stores_the_real_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $this->assertSame('203.0.113.9', InvalidLoginAttempt::first()->ip_address);
    }

    public function test_a_forwarded_address_from_the_internet_is_not_believed(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $this->assertSame('198.51.100.20', InvalidLoginAttempt::first()->ip_address);
    }
}
