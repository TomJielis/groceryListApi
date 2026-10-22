<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LastActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_request_marks_the_user_active(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->last_active_at);

        $this->actingAs($user, 'sanctum')->getJson('/api/grocery-list/index')->assertOk();

        $this->assertTrue($user->fresh()->last_active_at->isToday());
    }

    public function test_it_is_written_at_most_every_five_minutes(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['last_active_at' => now()->subMinutes(2)]);
        $twoMinutesAgo = $user->fresh()->last_active_at;

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/grocery-list/index')->assertOk();
        $this->assertTrue($user->fresh()->last_active_at->eq($twoMinutesAgo));

        DB::table('users')->where('id', $user->id)->update(['last_active_at' => now()->subMinutes(6)]);
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/grocery-list/index')->assertOk();
        $this->assertTrue($user->fresh()->last_active_at->gt(now()->subMinute()));
    }

    public function test_logging_out_keeps_the_last_activity(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/grocery-list/index')->assertOk();
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue($user->fresh()->last_active_at->isToday());
    }

    public function test_it_does_not_count_as_editing_the_user(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['updated_at' => '2026-01-01 00:00:00']);

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/grocery-list/index')->assertOk();

        $this->assertSame('2026-01-01 00:00:00', $user->fresh()->updated_at->format('Y-m-d H:i:s'));
    }
}
