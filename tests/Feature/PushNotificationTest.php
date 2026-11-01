<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

    public function test_sharing_a_list_with_an_existing_user_sends_a_push(): void
    {
        Queue::fake();
        Mail::fake();
        $owner = User::factory()->create(['name' => 'Tom', 'language' => 'nl']);
        $invited = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => $invited->email])
            ->assertOk();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $invited->id
            && $job->type === 'list_shared'
            && $job->replace === ['user' => 'Tom', 'list' => 'Weekend']);
    }

    public function test_sharing_with_someone_without_an_account_sends_no_push(): void
    {
        Queue::fake();
        Mail::fake();
        $owner = User::factory()->create(['language' => 'nl']);
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => 'new@example.com'])
            ->assertOk();

        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_accepting_an_invite_sends_a_push_to_the_owner(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $invited = User::factory()->create(['name' => 'Sam']);
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);
        GroceryListInvites::create([
            'grocery_list_id' => $list->id,
            'user_id' => $invited->id,
            'email' => $invited->email,
            'status' => GroceryListInvitesStatus::PENDING,
        ]);

        $this->actingAs($invited, 'sanctum')
            ->postJson('/api/grocery-list/pending/update-status', ['id' => $list->id, 'status' => GroceryListInvitesStatus::ACCEPTED])
            ->assertOk();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $owner->id
            && $job->type === 'invite_accepted'
            && $job->data === ['url' => '/list/' . $list->id]);
    }

    public function test_declining_an_invite_sends_no_push(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $invited = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);
        GroceryListInvites::create([
            'grocery_list_id' => $list->id,
            'user_id' => $invited->id,
            'email' => $invited->email,
            'status' => GroceryListInvitesStatus::PENDING,
        ]);

        $this->actingAs($invited, 'sanctum')
            ->postJson('/api/grocery-list/pending/update-status', ['id' => $list->id, 'status' => GroceryListInvitesStatus::DECLINED])
            ->assertOk();

        Queue::assertNotPushed(SendPushNotification::class);
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

        (new SendPushNotification($user->id, 'list_shared', ['user' => 'Tom', 'list' => 'Weekend'], ['url' => '/']))->handle();

        Http::assertSent(function (Request $request) {
            $first = json_decode($request->body(), true)[0];

            return $first['title'] === 'New shared list'
                && $first['body'] === 'Tom wants to share the grocery list "Weekend" with you.'
                && $first['data'] === ['url' => '/'];
        });
        $this->assertSame(['ExponentPushToken[alive]'], PushToken::pluck('token')->all());
    }

    public function test_job_does_nothing_for_a_user_without_devices(): void
    {
        Http::fake();

        (new SendPushNotification(User::factory()->create()->id, 'list_shared'))->handle();

        Http::assertNothingSent();
    }

    public function test_push_sets_the_badge_to_the_open_invites(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $owner = User::factory()->create();
        $user = User::factory()->create(['language' => 'nl']);
        PushToken::create(['user_id' => $user->id, 'token' => self::TOKEN]);
        // Two pending invites (one sent by email before signing up), one accepted and one declined that don't count
        $statuses = [
            [GroceryListInvitesStatus::PENDING, $user->id],
            [GroceryListInvitesStatus::PENDING, null],
            [GroceryListInvitesStatus::ACCEPTED, $user->id],
            [GroceryListInvitesStatus::DECLINED, $user->id],
        ];
        foreach ($statuses as [$status, $userId]) {
            $list = GroceryList::create(['name' => 'Lijst', 'created_by' => $owner->id]);
            GroceryListInvites::create([
                'grocery_list_id' => $list->id,
                'user_id' => $userId,
                'email' => $user->email,
                'status' => $status,
            ]);
        }

        (new SendPushNotification($user->id, 'list_shared', ['user' => 'Tom', 'list' => 'Lijst']))->handle();

        Http::assertSent(fn (Request $request) => json_decode($request->body(), true)[0]['badge'] === 2);
    }

    // An invite mail as the push's fallback, like the share endpoints send
    private function inviteJob(User $invited, User $owner): SendPushNotification
    {
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        return new SendPushNotification(
            $invited->id,
            'list_shared',
            ['user' => $owner->name, 'list' => $list->name],
            ['url' => '/'],
            new \App\Mail\GroceryListInviteMail($owner, $list, $invited, $invited->email),
            $invited->email,
        );
    }

    public function test_an_invite_push_that_reaches_a_device_sends_no_mail(): void
    {
        Mail::fake();
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $invited = User::factory()->create();
        PushToken::create(['user_id' => $invited->id, 'token' => self::TOKEN]);

        $this->inviteJob($invited, User::factory()->create())->handle();

        Http::assertSentCount(1);
        Mail::assertNothingSent();
    }

    public function test_the_invite_mail_goes_when_the_push_reaches_no_device(): void
    {
        Mail::fake();
        $owner = User::factory()->create();

        // No device at all (website only, notifications never allowed)
        $withoutDevice = User::factory()->create();
        $this->inviteJob($withoutDevice, $owner)->handle();
        Mail::assertSent(\App\Mail\GroceryListInviteMail::class, fn ($mail) => $mail->hasTo($withoutDevice->email));

        // Invite pushes switched off
        $switchedOff = User::factory()->create(['notification_settings' => ['invites' => false]]);
        PushToken::create(['user_id' => $switchedOff->id, 'token' => 'ExponentPushToken[off]']);
        $this->inviteJob($switchedOff, $owner)->handle();
        Mail::assertSent(\App\Mail\GroceryListInviteMail::class, fn ($mail) => $mail->hasTo($switchedOff->email));

        // Expo refuses it for every device (the app was deleted)
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]])]);
        $appDeleted = User::factory()->create();
        PushToken::create(['user_id' => $appDeleted->id, 'token' => 'ExponentPushToken[gone]']);
        $this->inviteJob($appDeleted, $owner)->handle();
        Mail::assertSent(\App\Mail\GroceryListInviteMail::class, fn ($mail) => $mail->hasTo($appDeleted->email));
        $this->assertDatabaseMissing('push_tokens', ['token' => 'ExponentPushToken[gone]']);
    }

    public function test_the_invite_mail_goes_when_sending_the_push_fails(): void
    {
        Mail::fake();
        Http::fake(['exp.host/*' => Http::response('Expo is down', 500)]);
        $invited = User::factory()->create();
        PushToken::create(['user_id' => $invited->id, 'token' => self::TOKEN]);

        // Run right away (no queue to try again): the mail goes instead
        $this->inviteJob($invited, User::factory()->create())->handle();

        Mail::assertSent(\App\Mail\GroceryListInviteMail::class);
    }

    public function test_sharing_mails_only_someone_without_an_account_and_pushes_the_others(): void
    {
        Queue::fake();
        Mail::fake();
        $owner = User::factory()->create();
        $invited = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);
        $cookbook = \App\Models\Cookbook::create(['name' => 'Samen', 'created_by' => $owner->id]);
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => $invited->email])->assertOk();
        $this->postJson("/api/cookbooks/{$cookbook->id}/share", ['email' => $invited->email])->assertOk();
        // With an account: only the push (which carries the mail as its fallback)
        Mail::assertNothingSent();
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->type === 'list_shared' && $job->fallbackEmail === $invited->email);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->type === 'cookbook_shared' && $job->fallbackEmail === $invited->email);

        // Without an account: the mail to register
        $this->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => 'nieuw@example.com'])->assertOk();
        $this->postJson("/api/cookbooks/{$cookbook->id}/share", ['email' => 'nieuw@example.com'])->assertOk();
        Mail::assertSent(\App\Mail\GroceryListInviteMail::class, fn ($mail) => $mail->hasTo('nieuw@example.com'));
        Mail::assertSent(\App\Mail\CookbookInviteMail::class, fn ($mail) => $mail->hasTo('nieuw@example.com'));
    }
}
