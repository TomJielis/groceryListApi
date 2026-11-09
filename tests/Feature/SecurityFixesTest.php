<?php

namespace Tests\Feature;

use App\Mail\AccountExists;
use App\Mail\Welcome;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private function sharedList(User $owner, User $member): GroceryList
    {
        $list = GroceryList::create(['name' => 'Samen', 'created_by' => $owner->id]);
        GroceryListInvites::create(['grocery_list_id' => $list->id, 'user_id' => $member->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);

        return $list;
    }

    private function registration(string $email): TestResponse
    {
        return $this->postJson('/api/register', [
            'name' => 'Sam',
            'email' => $email,
            'password' => 'longenough1',
            'language' => 'en',
            'acceptedTerms' => true,
            'acceptedTermsVersion' => '1',
        ]);
    }

    private function login(User $user, string $ip, string $password): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $user->email, 'password' => $password]);
    }

    public function test_a_member_sees_only_names_of_the_others_on_a_list(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $this->sharedList($owner, $member);

        $list = $this->actingAs($member, 'sanctum')->getJson('/api/grocery-list/index')->assertOk()->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($list['created_by']));
        $this->assertSame(['id', 'name'], array_keys($list['grocery_list_invites'][0]['user']));
    }

    public function test_the_owner_also_sees_the_members_address_but_nothing_more(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $this->sharedList($owner, $member);

        $user = $this->actingAs($owner, 'sanctum')->getJson('/api/grocery-list/index')->json('data.0.grocery_list_invites.0.user');

        $this->assertSame(['id' => $member->id, 'name' => $member->name, 'email' => $member->email], $user);
    }

    public function test_registering_an_address_that_has_an_account_looks_like_a_new_registration(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'taken@example.com', 'language' => 'nl']);

        $taken = $this->registration(' taken@example.com ')->assertOk();
        $new = $this->registration('new@example.com')->assertOk();

        $this->assertSame(array_keys($new->json()), array_keys($taken->json()));
        $this->assertSame(array_keys($new->json('user')), array_keys($taken->json('user')));
        $this->assertSame('taken@example.com', $taken->json('user.email'));
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
        Mail::assertSent(AccountExists::class, fn ($mail) => $mail->hasTo('taken@example.com'));
        Mail::assertSent(Welcome::class, fn ($mail) => $mail->hasTo('new@example.com'));
    }

    public function test_the_account_exists_mail_renders_in_both_languages(): void
    {
        foreach (['nl' => 'Inloggen', 'en' => 'Log in'] as $language => $button) {
            $user = User::factory()->create(['language' => $language]);
            $this->assertStringContainsString($button, (new AccountExists($user))->render());
        }
    }

    public function test_wrong_passwords_lock_only_the_address_they_came_from(): void
    {
        $user = User::factory()->create(['password' => 'correct-password', 'email_verified_at' => now()]);

        for ($i = 0; $i < 3; $i++) {
            $this->login($user, '198.51.100.1', 'wrong-password')->assertJsonPath('status', 401);
        }

        $this->login($user, '198.51.100.1', 'correct-password')->assertJsonPath('status', 429);
        $this->login($user, '203.0.113.9', 'correct-password')->assertJsonStructure(['access_token']);
    }

    public function test_item_values_are_validated(): void
    {
        $user = User::factory()->create();
        $list = GroceryList::create(['name' => 'Eigen', 'created_by' => $user->id]);
        $item = GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $list->id, 'checked' => false]);
        $this->actingAs($user, 'sanctum');

        foreach ([['quantity' => -1], ['quantity' => 'veel'], ['unit_price' => -2], ['unit_price' => 'gratis'], ['name' => ''], ['name' => ['x']]] as $bad) {
            $this->postJson("/api/grocery-list-item/{$item->id}/update", ['item' => $bad])->assertUnprocessable();
        }
        $this->postJson('/api/grocery-list-item/store', ['list_id' => $list->id])->assertUnprocessable();
        $this->postJson('/api/grocery-list-item/store', ['list_id' => $list->id, 'name' => 'Kaas', 'quantity' => -5])->assertUnprocessable();
        $this->postJson("/api/grocery-list-item/{$item->id}/checked", ['checked' => 'misschien'])->assertUnprocessable();

        $this->assertSame(1, GroceryListItem::count());
        $this->assertSame('Melk', $item->fresh()->name);
    }

    public function test_what_the_apps_send_still_works(): void
    {
        $user = User::factory()->create();
        $list = GroceryList::create(['name' => 'Eigen', 'created_by' => $user->id]);
        $item = GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $list->id, 'checked' => false]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/grocery-list-item/store', ['name' => 'kaas', 'quantity' => 1, 'list_id' => $list->id])->assertOk();
        $this->postJson("/api/grocery-list-item/{$item->id}/update", ['item' => ['id' => $item->id, 'name' => 'Melk', 'quantity' => '3', 'unit_price' => null]])->assertOk();
        $this->postJson("/api/grocery-list-item/{$item->id}/update", ['item' => ['id' => $item->id, 'unit_price' => '1.25']])->assertOk();
        // An emptied price field on the website is cleared, not refused
        $this->postJson("/api/grocery-list-item/{$item->id}/update", ['item' => ['unit_price' => '']])->assertOk();
        $this->postJson("/api/grocery-list-item/{$item->id}/checked", ['checked' => true])->assertOk();

        $this->assertSame(3, (int) $item->fresh()->quantity);
    }
}
