<?php

namespace Tests\Feature\Admin;

use App\Models\GroceryList;
use App\Models\InvalidLoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    }

    // blocked, is_admin and the terms version aren't fillable, so they're set directly
    private function user(array $attributes = []): User
    {
        $user = User::factory()->create(array_intersect_key($attributes, array_flip(['name', 'email'])));
        $user->forceFill(array_diff_key($attributes, array_flip(['name', 'email'])))->save();
        return $user;
    }

    private function usedTokenAt(User $user, $when): void
    {
        $user->createToken('test')->accessToken->forceFill(['last_used_at' => $when])->save();
    }

    private function overview(string $query = '')
    {
        return $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/users/overview' . ($query ? "?{$query}" : ''));
    }

    public function test_only_admins_can_see_it(): void
    {
        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/admin/users/overview')
            ->assertForbidden();
    }

    public function test_rows_carry_activity_lists_and_failed_logins(): void
    {
        $user = $this->user(['name' => 'Jan', 'email' => 'jan@example.com', 'accepted_terms_version' => config('app.terms_version')]);
        $this->usedTokenAt($user, now()->subDay());
        GroceryList::withoutGlobalScopes()->create(['name' => 'Weekend', 'created_by' => $user->id]);
        InvalidLoginAttempt::create(['user_id' => $user->id, 'username' => $user->email, 'ip_address' => '203.0.113.5', 'attempted_at' => now()]);
        InvalidLoginAttempt::create(['user_id' => $user->id, 'username' => $user->email, 'ip_address' => '203.0.113.5', 'attempted_at' => now()->subDays(10)]);

        $this->overview('search=jan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jan')
            ->assertJsonPath('data.0.lists_count', 1)
            ->assertJsonPath('data.0.failed_logins', 1)
            ->assertJsonPath('data.0.terms_current', true)
            ->assertJsonPath('summary.total', 2);
    }

    public function test_filters_on_status_and_terms(): void
    {
        $active = $this->user(['email' => 'active@example.com', 'accepted_terms_version' => config('app.terms_version')]);
        $this->usedTokenAt($active, now()->subDays(2));
        $stale = $this->user(['email' => 'stale@example.com', 'accepted_terms_version' => '1.0']);
        $this->usedTokenAt($stale, now()->subDays(60));
        $this->user(['email' => 'blocked@example.com', 'blocked' => true]);
        User::factory()->unverified()->create(['email' => 'new@example.com']);

        $emails = fn (string $query) => collect($this->overview($query)->assertOk()->json('data'))->pluck('email')->sort()->values()->all();

        $this->assertSame(['active@example.com'], $emails('status=active'));
        $this->assertNotContains('active@example.com', $emails('status=inactive'));
        $this->assertContains('stale@example.com', $emails('status=inactive'));
        $this->assertSame(['new@example.com'], $emails('status=unverified'));
        $this->assertSame(['blocked@example.com'], $emails('status=blocked'));
        $this->assertSame(['active@example.com'], $emails('terms=current'));
        $this->assertContains('stale@example.com', $emails('terms=outdated'));

        $this->overview()
            ->assertJsonPath('summary.total', 5)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('summary.unverified', 1)
            ->assertJsonPath('summary.blocked', 1);
    }

    public function test_sorts_on_last_activity_with_never_active_users_last(): void
    {
        $earlier = $this->user(['email' => 'earlier@example.com']);
        $this->usedTokenAt($earlier, now()->subDays(5));
        $latest = $this->user(['email' => 'latest@example.com']);
        $this->usedTokenAt($latest, now()->subHour());

        $emails = collect($this->overview()->json('data'))->pluck('email')->all();

        $this->assertSame(['latest@example.com', 'earlier@example.com'], array_slice($emails, 0, 2));
    }

    public function test_the_old_users_list_keeps_its_shape(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['users' => [['id', 'name', 'email', 'last_active', 'lists_count', 'blocked']], 'total']);
    }
}
