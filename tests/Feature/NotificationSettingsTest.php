<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_everything_is_on_by_default(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/user/notification-settings')
            ->assertOk()
            ->assertExactJson(['data' => [
                'invites' => true,
                'list_changes' => true,
                'items_added' => true,
                'items_checked' => true,
                'list_completed' => true,
            ]]);
    }

    public function test_a_setting_can_be_switched_off_without_touching_the_others(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/user/notification-settings', ['items_added' => false])
            ->assertOk()
            ->assertJsonPath('data.items_added', false)
            ->assertJsonPath('data.items_checked', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/user/notification-settings', ['items_checked' => false])
            ->assertOk()
            ->assertJsonPath('data.items_added', false)
            ->assertJsonPath('data.items_checked', false)
            ->assertJsonPath('data.invites', true);
    }

    public function test_rejects_values_that_are_not_on_or_off(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/user/notification-settings', ['items_added' => 'maybe'])
            ->assertUnprocessable();
    }

    public function test_unknown_settings_are_ignored(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/user/notification-settings', ['something_else' => false])
            ->assertOk()
            ->assertJsonMissingPath('data.something_else');
    }

    public function test_no_push_is_sent_for_a_switched_off_setting(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $user = User::factory()->create(['language' => 'nl']);
        $user->notification_settings = ['items_added' => false];
        $user->save();
        PushToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]']);

        (new SendPushNotification($user->id, 'items_added', ['user' => 'Tom', 'list' => 'Weekend', 'count' => 2]))->handle();
        Http::assertNothingSent();

        // Other kinds still arrive
        (new SendPushNotification($user->id, 'list_shared', ['user' => 'Tom', 'list' => 'Weekend']))->handle();
        Http::assertSentCount(1);
    }
}
