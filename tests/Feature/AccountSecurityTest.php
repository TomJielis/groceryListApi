<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\SentEmail;
use App\Models\TemporaryPasswordCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_blocking_ends_the_sessions_that_are_open(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $user->createToken('phone');

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$user->id}/block")->assertOk();
        $this->assertSame(0, $user->tokens()->count());

        // Unblocking keeps working as before
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$user->id}/block")->assertOk();
        $this->assertFalse((bool) $user->fresh()->blocked);
    }

    private function resetCode(User $user, $createdAt): string
    {
        $code = new TemporaryPasswordCode();
        $code->user_id = $user->id;
        $code->code = 'code-' . $user->id . '-' . $createdAt->timestamp;
        $code->is_used = false;
        $code->save();
        $code->forceFill(['created_at' => $createdAt])->save();
        return $code->code;
    }

    public function test_a_reset_link_works_within_an_hour_and_logs_out_other_devices(): void
    {
        $user = User::factory()->create();
        $user->createToken('old phone');
        $token = $this->resetCode($user, now()->subMinutes(30));

        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => $token, 'password' => 'new-password'])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_old_reset_link_no_longer_works(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $token = $this->resetCode($user, now()->subMinutes(61));

        $this->postJson('/api/set-new-password', ['email' => $user->email, 'token' => $token, 'password' => 'new-password'])
            ->assertStatus(400);

        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_an_unknown_email_gets_an_answer_instead_of_a_server_error(): void
    {
        $this->postJson('/api/set-new-password', ['email' => 'nobody@example.com', 'token' => 'x', 'password' => 'new-password'])
            ->assertStatus(400);
    }

    public function test_the_email_log_keeps_no_working_reset_link(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', ['email' => $user->email])->assertOk();

        $logged = SentEmail::where('type', 'reset_password')->firstOrFail();
        $this->assertSame('sent', $logged->status);
        $this->assertNull($logged->body_html);
        $this->assertNull($logged->body_text);
    }

    public function test_sharing_with_someone_who_already_joined_sends_nothing(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);
        GroceryListInvites::create(['grocery_list_id' => $list->id, 'user_id' => $member->id, 'email' => $member->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => $member->email])
            ->assertOk();

        $this->assertDatabaseCount('grocery_list_invites', 1);
        $this->assertDatabaseCount('sent_emails', 0);
    }

    public function test_invites_to_addresses_without_an_account_stay_separate(): void
    {
        // The invite email is written in the sender's language (every real account has one)
        $owner = User::factory()->create(['language' => 'nl']);
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        foreach (['a@example.com', 'b@example.com'] as $email) {
            $this->actingAs($owner, 'sanctum')
                ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => $email])
                ->assertOk();
        }

        $this->assertEqualsCanonicalizing(
            ['a@example.com', 'b@example.com'],
            GroceryListInvites::pluck('email')->all()
        );
    }

    public function test_inviting_the_same_person_again_reuses_their_invite(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($owner, 'sanctum')
                ->postJson('/api/grocery-list/share', ['groceryListId' => $list->id, 'email' => $friend->email])
                ->assertOk();
        }

        $this->assertDatabaseCount('grocery_list_invites', 1);
    }
}
