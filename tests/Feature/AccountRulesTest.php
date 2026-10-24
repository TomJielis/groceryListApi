<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\PushToken;
use App\Models\TemporaryPasswordCode;
use App\Models\User;
use App\Jobs\SendPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountRulesTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $data, string $ip = '203.0.113.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/register', array_merge([
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'long-enough',
            'language' => 'nl',
            'acceptedTerms' => true,
            'acceptedTermsVersion' => '1.1',
        ], $data));
    }

    // ---- S8: registration ----

    public function test_registration_checks_the_fields_in_the_users_language(): void
    {
        $this->register(['email' => 'not-an-email'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Vul een geldig e-mailadres in.');

        $this->register(['password' => 'short', 'language' => 'en'])
            ->assertJsonPath('error', 'Your password must be at least 8 characters.');

        $this->register(['name' => ''])->assertJsonPath('error', 'Vul je naam in.');

        $this->assertSame(0, User::count());
    }

    public function test_a_second_account_for_the_same_email_is_refused(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        $this->register(['email' => ' sam@example.com '])
            ->assertOk()
            ->assertJsonPath('error', 'Er bestaat al een account met dit e-mailadres.');

        $this->assertSame(1, User::count());
    }

    public function test_a_valid_registration_still_works(): void
    {
        $this->register([])->assertOk()->assertJsonPath('user.email', 'sam@example.com');
    }

    // ---- S7: password reset ----

    public function test_an_unknown_email_gets_the_same_answer_as_a_known_one(): void
    {
        $this->postJson('/api/reset-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Wachtwoordherstel-e-mail verzonden');

        $this->assertSame(0, TemporaryPasswordCode::count());
    }

    public function test_a_new_password_must_be_long_enough_and_repeated_correctly(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $code = new TemporaryPasswordCode();
        $code->user_id = $user->id;
        $code->code = 'reset-code';
        $code->is_used = false;
        $code->save();

        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => 'reset-code', 'password' => ''])
            ->assertStatus(400);
        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => 'reset-code', 'password' => 'long-enough', 'repeatPassword' => 'different!'])
            ->assertStatus(400);
        $this->assertSame($password, $user->fresh()->password);

        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => 'reset-code', 'password' => 'long-enough', 'repeatPassword' => 'long-enough'])
            ->assertOk();
        $this->assertTrue(Hash::check('long-enough', $user->fresh()->password));
    }

    // ---- S5: rate limiting ----

    private function login(string $email, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $email, 'password' => 'wrong']);
    }

    public function test_logins_for_one_email_are_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->login('victim@example.com', '203.0.113.' . $i)->assertJsonMissing(['status' => 429]);
        }

        $this->login('victim@example.com', '198.51.100.1')
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 429);
    }

    public function test_logins_from_one_address_are_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->login("guess{$i}@example.com", '203.0.113.9')->assertJsonMissing(['status' => 429]);
        }

        $this->login('guess20@example.com', '203.0.113.9')->assertJsonPath('status', 429);
        // Someone else is not affected
        $this->login('guess20@example.com', '198.51.100.1')->assertJsonMissing(['status' => 429]);
    }

    public function test_the_websites_own_server_only_counts_per_email(): void
    {
        // Requests the website's server makes without the visitor's address all come from this machine
        for ($i = 0; $i < 25; $i++) {
            $this->login("visitor{$i}@example.com", '127.0.0.1')->assertJsonMissing(['status' => 429]);
        }
    }

    public function test_password_reset_emails_are_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/reset-password', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/api/reset-password', ['email' => $user->email])->assertStatus(429);
    }

    public function test_registrations_are_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->register(['email' => "new{$i}@example.com"])->assertJsonPath('user.email', "new{$i}@example.com");
        }

        $this->register(['email' => 'new10@example.com'])->assertJsonPath('status', 429);
    }

    public function test_invites_are_limited_per_user(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['language' => 'nl']);
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($owner, 'sanctum')
                ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => "friend{$i}@example.com"])
                ->assertOk();
        }

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => 'friend30@example.com'])
            ->assertStatus(429);
    }

    // ---- Cards and item amounts ----

    public function test_a_card_image_has_a_size_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cards/store', ['title' => 'Big', 'attachment' => str_repeat('a', 10_000_001)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'De afbeelding is te groot. Kies een kleinere foto.');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cards/store', ['title' => 'Jumbo', 'attachment' => 'data:image/png;base64,abc'])
            ->assertOk();
    }

    public function test_amounts_must_be_positive(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $user->id]);
        $item = GroceryListItem::create(['name' => 'Melk', 'quantity' => 2, 'list_id' => $list->id, 'checked' => false]);

        $this->actingAs($user, 'sanctum')->postJson("/api/grocery-list-item/{$item->id}/decrease", ['amount' => -5])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->postJson("/api/grocery-list-item/{$item->id}/increase", ['amount' => 0])->assertUnprocessable();
        $this->assertSame(2, (int) $item->fresh()->quantity);

        // As the app and the website call them: without an amount
        $this->actingAs($user, 'sanctum')->postJson("/api/grocery-list-item/{$item->id}/increase")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/grocery-list-item/{$item->id}/decrease")->assertOk();
        $this->assertSame(2, (int) $item->fresh()->quantity);
    }

    // ---- B2: stats month ----

    public function test_asking_for_a_short_month_on_the_31st_gives_that_month(): void
    {
        // (User and admin stats parse the month the same way; the user stats query needs MySQL)
        $this->travelTo(now()->setDate(2026, 3, 31));
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/stats/spend?month=2026-02')
            ->assertOk()
            ->assertJsonPath('current_month.period', '2026-02')
            ->assertJsonPath('previous_month.period', '2026-01');
    }

    // ---- B9: pushes stop when the login ends ----

    public function test_a_push_token_stops_when_its_login_ends(): void
    {
        $user = User::factory()->create();
        $login = $user->createToken('phone')->plainTextToken;

        $this->withToken($login)->postJson('/api/push-tokens', ['token' => 'ExponentPushToken[abc]'])->assertOk();
        $this->assertNotNull(PushToken::sole()->access_token_id);

        // Blocking, a password reset and logging out all delete the login
        $user->tokens()->delete();

        $this->assertSame(0, PushToken::count());
    }

    public function test_no_push_goes_to_a_device_whose_login_expired(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $user = User::factory()->create(['language' => 'nl']);
        $login = $user->createToken('phone', ['*'], now()->addDays(14));
        PushToken::create(['user_id' => $user->id, 'access_token_id' => $login->accessToken->id, 'token' => 'ExponentPushToken[abc]']);

        $this->travel(15)->days();
        (new SendPushNotification($user->id, 'list_shared', ['user' => 'Tom', 'list' => 'Weekend']))->handle();

        Http::assertNothingSent();
        $this->assertSame(0, PushToken::count());
    }

    // ---- Reopening an email link ----

    public function test_reopening_a_verification_link_says_the_account_is_already_verified(): void
    {
        $user = User::factory()->create();
        $link = \Illuminate\Support\Facades\Crypt::encryptString($user->id);

        $this->getJson('/api/verify-email/' . $link)
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'already_verified');
    }

    public function test_a_broken_verification_link_is_not_a_server_error(): void
    {
        $this->getJson('/api/verify-email/not-a-real-link')
            ->assertOk()
            ->assertJsonPath('reason', 'invalid_link');
    }

    public function test_a_used_reset_link_says_so(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => 'used-before', 'password' => 'long-enough'])
            ->assertStatus(400)
            ->assertJsonPath('reason', 'invalid_link');
    }

    public function test_a_reset_link_can_be_checked_before_using_it(): void
    {
        $user = User::factory()->create();
        foreach (['fresh' => now(), 'old' => now()->subMinutes(61)] as $code => $createdAt) {
            $row = new TemporaryPasswordCode();
            $row->user_id = $user->id;
            $row->code = $code;
            $row->is_used = false;
            $row->save();
            $row->forceFill(['created_at' => $createdAt])->save();
        }

        $this->postJson('/api/check-reset-link', ['token' => 'fresh'])->assertOk()->assertExactJson(['valid' => true]);
        $this->postJson('/api/check-reset-link', ['token' => 'old'])->assertExactJson(['valid' => false]);
        $this->postJson('/api/check-reset-link', ['token' => 'never-sent'])->assertExactJson(['valid' => false]);

        // Once used, the same link is no longer valid
        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => 'fresh', 'password' => 'long-enough'])->assertOk();
        $this->postJson('/api/check-reset-link', ['token' => 'fresh'])->assertExactJson(['valid' => false]);
    }
}
