<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// Invite links: a list or cookbook shared with a link instead of an email
class InviteLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.url' => 'https://example.com']);
        $this->owner = User::factory()->create(['name' => 'Tom']);
        $this->friend = User::factory()->create();
    }

    private function tokenOf(string $url): string
    {
        return basename($url);
    }

    public function test_a_list_link_shows_what_it_is_and_joins_whoever_accepts_it(): void
    {
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->owner->id]);

        $url = $this->actingAs($this->owner, 'sanctum')->postJson("/api/grocery-list/{$list->id}/invite-link")
            ->assertOk()->json('data.url');
        $this->assertStringStartsWith('https://example.com/auth/invite/', $url);
        $token = $this->tokenOf($url);
        // Sharing again gives the same link
        $this->assertSame($url, $this->postJson("/api/grocery-list/{$list->id}/invite-link")->json('data.url'));

        // Also before logging in: what it invites to
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/invite-links/{$token}")->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.type', 'list')
            ->assertJsonPath('data.name', 'Weekend')
            ->assertJsonPath('data.invited_by', 'Tom')
            ->assertJsonPath('data.already_member', null);

        $this->actingAs($this->friend, 'sanctum')->postJson("/api/invite-links/{$token}/accept")
            ->assertOk()->assertJsonPath('data.type', 'list')->assertJsonPath('data.id', $list->id)
            ->assertJsonPath('data.name', 'Weekend')->assertJsonPath('data.already_member', false);
        $this->assertContains($this->friend->id, $list->memberIds());
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->owner->id && $job->type === 'invite_accepted');

        // Accepting again changes nothing
        $this->postJson("/api/invite-links/{$token}/accept")->assertOk()->assertJsonPath('data.already_member', true);
        $this->assertSame(1, GroceryListInvites::where('user_id', $this->friend->id)->count());
        $this->assertTrue($this->getJson("/api/invite-links/{$token}")->json('data.already_member'));
    }

    public function test_a_cookbook_link_is_for_its_creator_and_joins_the_cookbook(): void
    {
        $cookbook = Cookbook::create(['name' => 'Samen koken', 'created_by' => $this->owner->id]);
        $token = $this->tokenOf($this->actingAs($this->owner, 'sanctum')->postJson("/api/cookbooks/{$cookbook->id}/invite-link")->assertOk()->json('data.url'));

        $this->actingAs($this->friend, 'sanctum')->postJson("/api/invite-links/{$token}/accept")
            ->assertOk()->assertJsonPath('data.type', 'cookbook')->assertJsonPath('data.name', 'Samen koken');
        $this->assertContains($this->friend->id, $cookbook->memberIds());
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->owner->id && $job->type === 'cookbook_invite_accepted');

        // Someone in it who isn't the creator can't make a link
        $this->postJson("/api/cookbooks/{$cookbook->id}/invite-link")->assertForbidden();
    }

    public function test_an_expired_or_withdrawn_link_says_so_and_joins_nobody(): void
    {
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->owner->id]);
        $token = $this->tokenOf($this->actingAs($this->owner, 'sanctum')->postJson("/api/grocery-list/{$list->id}/invite-link")->json('data.url'));

        $this->travel(InviteLink::DAYS_VALID + 1)->days();
        $this->actingAs($this->friend, 'sanctum');
        $this->getJson("/api/invite-links/{$token}")->assertOk()->assertJsonPath('data.status', 'expired');
        $this->postJson("/api/invite-links/{$token}/accept")->assertStatus(410)
            ->assertJsonPath('message', 'Deze uitnodigingslink is verlopen of ingetrokken. Vraag om een nieuwe link.');
        $this->assertNotContains($this->friend->id, $list->memberIds());
        $this->travelBack();
        InviteLink::where('token', $token)->update(['expires_at' => now()->subMinute()]);

        // A new link after expiry, then withdrawn by its maker
        $fresh = $this->tokenOf($this->actingAs($this->owner, 'sanctum')->postJson("/api/grocery-list/{$list->id}/invite-link")->json('data.url'));
        $this->assertNotSame($token, $fresh);
        $this->actingAs($this->friend, 'sanctum')->deleteJson("/api/invite-links/{$fresh}/delete")->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/invite-links/{$fresh}/delete")->assertOk();
        $this->getJson("/api/invite-links/{$fresh}")->assertJsonPath('data.status', 'expired');

        $this->getJson('/api/invite-links/does-not-exist')->assertJsonPath('data.status', 'not_found');
        $this->actingAs($this->friend, 'sanctum')->postJson('/api/invite-links/does-not-exist/accept')->assertNotFound();
    }

    public function test_only_someone_in_a_list_can_make_its_link_and_deleting_it_ends_the_link(): void
    {
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->owner->id]);
        $this->actingAs($this->friend, 'sanctum')->postJson("/api/grocery-list/{$list->id}/invite-link")->assertNotFound();

        $token = $this->tokenOf($this->actingAs($this->owner, 'sanctum')->postJson("/api/grocery-list/{$list->id}/invite-link")->json('data.url'));
        $list->delete();

        $this->getJson("/api/invite-links/{$token}")->assertJsonPath('data.status', 'not_found');
    }
}
