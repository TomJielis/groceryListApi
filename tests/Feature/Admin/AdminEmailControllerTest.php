<?php

namespace Tests\Feature\Admin;

use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_returns_paginated_emails(): void
    {
        SentEmail::factory()->count(25)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);

        $this->assertCount(20, $response->json('data'));
    }

    public function test_filters_by_status(): void
    {
        SentEmail::factory()->count(3)->create(['status' => 'sent']);
        SentEmail::factory()->count(2)->failed()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails?status=failed');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filters_by_type(): void
    {
        SentEmail::factory()->count(3)->create(['type' => 'welcome']);
        SentEmail::factory()->count(2)->create(['type' => 'reset_password']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails?type=welcome');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_limit_returns_n_most_recent_without_pagination_meta(): void
    {
        SentEmail::factory()->count(15)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails?limit=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertArrayNotHasKey('meta', $response->json());
    }

    public function test_non_admin_receives_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/emails')
            ->assertForbidden();
    }

    public function test_includes_triggered_by_user(): void
    {
        $user = User::factory()->create();
        SentEmail::factory()->create(['triggered_by_user_id' => $user->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails');

        $response->assertOk();
        $this->assertNotNull($response->json('data.0.triggered_by'));
        $this->assertEquals($user->name, $response->json('data.0.triggered_by.name'));
    }
}
