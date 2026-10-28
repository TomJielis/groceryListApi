<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Deleting an account removes the user and everything that is theirs, and nothing of anyone else
 */
class DeactivateAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $friend;
    private GroceryList $ownList;
    private GroceryList $friendsList;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user = User::factory()->create();
        $this->friend = User::factory()->create();

        // A list of their own, shared with the friend
        $this->ownList = GroceryList::create(['name' => 'Eigen', 'created_by' => $this->user->id]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $this->ownList->id, 'created_by' => $this->user->id]);
        GroceryListInvites::create(['grocery_list_id' => $this->ownList->id, 'user_id' => $this->friend->id, 'email' => $this->friend->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);

        // The friend's list they are a member of, with a product they added
        $this->friendsList = GroceryList::create(['name' => 'Van vriend', 'created_by' => $this->friend->id]);
        GroceryListInvites::create(['grocery_list_id' => $this->friendsList->id, 'user_id' => $this->user->id, 'email' => $this->user->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        GroceryListItem::create(['name' => 'Brood', 'quantity' => 1, 'list_id' => $this->friendsList->id, 'created_by' => $this->user->id]);

        Card::create(['user_id' => $this->user->id, 'title' => 'Bonuskaart', 'attachment' => 'data:']);
        Card::create(['user_id' => $this->friend->id, 'title' => 'Van vriend', 'attachment' => 'data:']);
        $this->user->createToken('phone');
        $this->friend->createToken('phone');
    }

    private function deactivate()
    {
        return $this->actingAs($this->user, 'sanctum')->postJson('/api/deactivate');
    }

    public function test_the_user_and_their_own_things_are_removed(): void
    {
        $this->deactivate()->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        $this->assertDatabaseMissing('grocery_lists', ['id' => $this->ownList->id]);
        $this->assertDatabaseMissing('grocery_list_items', ['list_id' => $this->ownList->id]);
        $this->assertDatabaseMissing('grocery_list_invites', ['grocery_list_id' => $this->ownList->id]);
        $this->assertDatabaseMissing('grocery_list_invites', ['user_id' => $this->user->id]);
        $this->assertDatabaseMissing('cards', ['user_id' => $this->user->id]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->user->id)->count());
    }

    public function test_their_login_data_and_mails_are_removed(): void
    {
        $now = now()->toDateTimeString();
        foreach ([$this->user, $this->friend] as $person) {
            DB::table('successful_logins')->insert(['user_id' => $person->id, 'ip_address' => '203.0.113.7', 'client' => 'app', 'logged_in_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('invalid_login_attempts')->insert(['user_id' => $person->id, 'username' => $person->email, 'ip_address' => '203.0.113.7', 'attempted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('sent_emails')->insert(['type' => 'reset_password', 'recipient_email' => $person->email, 'subject' => 'Reset', 'status' => 'sent', 'created_at' => $now, 'updated_at' => $now]);
        }
        // A failed attempt with their address but no user id (e.g. before the account existed)
        DB::table('invalid_login_attempts')->insert(['user_id' => null, 'username' => $this->user->email, 'ip_address' => '203.0.113.8', 'attempted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

        $this->deactivate()->assertOk();

        $this->assertDatabaseMissing('successful_logins', ['user_id' => $this->user->id]);
        $this->assertDatabaseMissing('invalid_login_attempts', ['username' => $this->user->email]);
        $this->assertDatabaseMissing('sent_emails', ['recipient_email' => $this->user->email]);
        // The friend's stay
        $this->assertDatabaseHas('successful_logins', ['user_id' => $this->friend->id]);
        $this->assertDatabaseHas('invalid_login_attempts', ['username' => $this->friend->email]);
        $this->assertDatabaseHas('sent_emails', ['recipient_email' => $this->friend->email]);
    }

    public function test_other_peoples_things_stay(): void
    {
        $this->deactivate()->assertOk();

        $this->assertDatabaseHas('users', ['id' => $this->friend->id]);
        // The friend's list stays, with what the user added to it
        $this->assertDatabaseHas('grocery_lists', ['id' => $this->friendsList->id]);
        $this->assertDatabaseHas('grocery_list_items', ['list_id' => $this->friendsList->id, 'name' => 'Brood']);
        $this->assertDatabaseHas('cards', ['user_id' => $this->friend->id]);
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $this->friend->id)->count());
    }

    public function test_a_friend_whose_favorite_was_the_deleted_list_has_no_favorite_anymore(): void
    {
        $this->friend->forceFill(['favorite_list_id' => $this->ownList->id])->save();

        $this->deactivate()->assertOk();

        $this->assertNull($this->friend->fresh()->favorite_list_id);
    }

    public function test_it_needs_a_login(): void
    {
        $this->postJson('/api/deactivate')->assertUnauthorized();
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }
}
