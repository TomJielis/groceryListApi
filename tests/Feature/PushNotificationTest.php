<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ExponentPushToken[abc123]';

    public function test_stores_the_push_token_of_a_device(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/push-tokens', ['token' => self::TOKEN])
            ->assertOk();

        $this->assertDatabaseHas('push_tokens', ['user_id' => $user->id, 'token' => self::TOKEN]);
    }

    public function test_a_device_that_switches_account_moves_to_the_new_user(): void
    {
        $previous = User::factory()->create();
        $current = User::factory()->create();
        PushToken::create(['user_id' => $previous->id, 'token' => self::TOKEN]);

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/push-tokens', ['token' => self::TOKEN])
            ->assertOk();

        $this->assertSame([$current->id], PushToken::pluck('user_id')->all());
    }

    public function test_rejects_something_that_is_not_an_expo_push_token(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/push-tokens', ['token' => 'not-a-token'])
            ->assertUnprocessable();
    }

    public function test_logout_removes_the_push_token_of_that_device(): void
    {
        $user = User::factory()->create();
        PushToken::create(['user_id' => $user->id, 'token' => self::TOKEN]);
        $accessToken = $user->createToken('phone')->plainTextToken;

        $this->withToken($accessToken)
            ->postJson('/api/logout', ['pushToken' => self::TOKEN])
            ->assertOk();

        $this->assertDatabaseCount('push_tokens', 0);
    }

    public function test_job_sends_the_text_in_the_users_language_and_drops_dead_tokens(): void
    {
        Http::fake([
            'exp.host/*' => Http::response(['data' => [
                ['status' => 'ok', 'id' => 'ticket-1'],
                ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
            ]]),
        ]);
        $user = User::factory()->create(['language' => 'en']);
        PushToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[alive]']);
        PushToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[uninstalled]']);

        (new SendPushNotification($user->id, 'invite_accepted', ['user' => 'Tom', 'list' => 'Weekend'], ['url' => '/']))->handle();

        Http::assertSent(function (Request $request) {
            $first = json_decode($request->body(), true)[0];

            return $first['title'] === 'Invite accepted'
                && $first['body'] === 'Tom has joined your grocery list "Weekend".'
                && $first['data'] === ['url' => '/'];
        });
        $this->assertSame(['ExponentPushToken[alive]'], PushToken::pluck('token')->all());
    }

    public function test_job_does_nothing_for_a_user_without_devices(): void
    {
        Http::fake();

        (new SendPushNotification(User::factory()->create()->id, 'invite_accepted'))->handle();

        Http::assertNothingSent();
    }

    public function test_push_clears_the_badge(): void
    {
        // Nothing waits for the user any more (invites are links): every push clears a badge left from email invites
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $user = User::factory()->create(['language' => 'nl']);
        PushToken::create(['user_id' => $user->id, 'token' => self::TOKEN]);

        (new SendPushNotification($user->id, 'invite_accepted', ['user' => 'Tom', 'list' => 'Lijst']))->handle();

        Http::assertSent(fn (Request $request) => json_decode($request->body(), true)[0]['badge'] === 0);
    }
}
