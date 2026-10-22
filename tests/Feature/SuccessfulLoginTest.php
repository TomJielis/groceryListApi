<?php

namespace Tests\Feature;

use App\Models\InvalidLoginAttempt;
use App\Models\SuccessfulLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuccessfulLoginTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password, string $userAgent, string $ip = '203.0.113.9')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', $userAgent)
            ->postJson('/api/login', ['email' => $user->email, 'password' => $password]);
    }

    public function test_a_login_from_the_app_is_recorded_with_its_build(): void
    {
        $user = User::factory()->create();

        $this->login($user, 'password', 'Boodschappenvriendje/28 CFNetwork/3896.100.1.2.1 Darwin/27.0.0')
            ->assertJsonStructure(['access_token']);

        $login = SuccessfulLogin::sole();
        $this->assertSame($user->id, $login->user_id);
        $this->assertSame('203.0.113.9', $login->ip_address);
        $this->assertSame('app', $login->client);
        $this->assertSame('28', $login->app_version);
    }

    public function test_a_login_through_the_website_is_recorded_as_website(): void
    {
        $user = User::factory()->create();

        $this->login($user, 'password', 'node');

        $this->assertSame('website', SuccessfulLogin::sole()->client);
    }

    public function test_a_failed_login_is_not_recorded_as_successful(): void
    {
        $user = User::factory()->create();

        $this->login($user, 'wrong', 'node');

        $this->assertSame(0, SuccessfulLogin::count());
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    private function failures(string $ip, int $count, $at): void
    {
        for ($i = 0; $i < $count; $i++) {
            InvalidLoginAttempt::create(['user_id' => null, 'username' => "guess{$i}@example.com", 'ip_address' => $ip, 'attempted_at' => $at]);
        }
    }

    public function test_a_success_after_several_failures_from_one_address_is_flagged(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com']);
        $this->failures('198.51.100.7', 3, now()->subHour());
        SuccessfulLogin::create(['user_id' => $victim->id, 'ip_address' => '198.51.100.7', 'client' => 'website', 'logged_in_at' => now()]);

        // Not flagged: two typos before logging in, a success from before the failures, and local addresses
        $this->failures('203.0.113.20', 2, now()->subHour());
        SuccessfulLogin::create(['user_id' => $victim->id, 'ip_address' => '203.0.113.20', 'client' => 'app', 'logged_in_at' => now()]);
        $this->failures('203.0.113.30', 5, now()->subHour());
        SuccessfulLogin::create(['user_id' => $victim->id, 'ip_address' => '203.0.113.30', 'client' => 'app', 'logged_in_at' => now()->subDays(2)]);
        $this->failures('127.0.0.1', 5, now()->subHour());
        SuccessfulLogin::create(['user_id' => $victim->id, 'ip_address' => '127.0.0.1', 'client' => 'website', 'logged_in_at' => now()]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/login-attempts?period=7d')
            ->assertOk()
            ->assertJsonCount(1, 'summary.suspicious')
            ->assertJsonPath('summary.suspicious.0.ip_address', '198.51.100.7')
            ->assertJsonPath('summary.suspicious.0.failed_attempts', 3)
            ->assertJsonPath('summary.suspicious.0.accounts.0.email', 'victim@example.com');
    }

    public function test_the_user_detail_shows_recent_logins(): void
    {
        $user = User::factory()->create();
        SuccessfulLogin::create(['user_id' => $user->id, 'ip_address' => '203.0.113.9', 'client' => 'app', 'app_version' => '28', 'logged_in_at' => now()]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('recent_logins.0.ip_address', '203.0.113.9')
            ->assertJsonPath('recent_logins.0.client', 'app')
            ->assertJsonPath('recent_logins.0.app_version', '28');
    }
}
