<?php

namespace Tests\Feature;

use App\Jobs\SendListActivityPush;
use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ListActivityPushTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private GroceryList $list;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->owner = User::factory()->create(['name' => 'Tom', 'language' => 'nl']);
        $this->member = User::factory()->create(['name' => 'Sam', 'language' => 'nl']);
        $this->list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->owner->id]);
        GroceryListInvites::create([
            'grocery_list_id' => $this->list->id,
            'user_id' => $this->member->id,
            'email' => $this->member->email,
            'status' => GroceryListInvitesStatus::ACCEPTED,
        ]);
    }

    public function test_adding_items_is_grouped_into_one_push_for_the_other_members(): void
    {
        foreach (['Melk', 'Brood', 'Kaas'] as $name) {
            $this->actingAs($this->owner, 'sanctum')
                ->postJson('/api/grocery-list-item/store', ['name' => $name, 'list_id' => $this->list->id])
                ->assertOk();
        }

        // One delayed group job for all three items
        Queue::assertPushed(SendListActivityPush::class, 1);
        $this->runGroupJobs();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->member->id
            && $job->type === 'items_added'
            && $job->replace === ['user' => 'Tom', 'list' => 'Weekend', 'count' => 3]
            && $job->data === ['url' => '/list/' . $this->list->id]);
        // The person adding the items doesn't get a push about it
        Queue::assertNotPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->owner->id);
    }

    public function test_the_group_waits_two_minutes(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/grocery-list-item/store', ['name' => 'Melk', 'list_id' => $this->list->id]);

        Queue::assertPushed(SendListActivityPush::class, fn ($job) => $job->delay !== null
            && now()->diffInSeconds($job->delay) >= 110);
    }

    public function test_checking_off_items_is_grouped(): void
    {
        $items = $this->createItems(['Melk', 'Brood', 'Kaas']);

        $this->check($this->member, $items[0]);
        $this->check($this->member, $items[1]);
        $this->runGroupJobs();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->owner->id
            && $job->type === 'items_checked'
            && $job->replace['count'] === 2);
    }

    public function test_checking_off_the_last_item_sends_finished_shopping_instead(): void
    {
        $items = $this->createItems(['Melk', 'Brood']);

        $this->check($this->member, $items[0]);
        $this->check($this->member, $items[1]);

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->owner->id
            && $job->type === 'list_completed'
            && $job->replace === ['user' => 'Sam', 'list' => 'Weekend']);

        // The pending "checked off 1 item" push is dropped: "finished shopping" already said it all
        $this->runGroupJobs();
        Queue::assertNotPushed(SendPushNotification::class, fn ($job) => $job->type === 'items_checked');
    }

    public function test_unchecking_an_item_sends_nothing(): void
    {
        $item = $this->createItems(['Melk'])[0];
        $item->update(['checked' => true]);

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/grocery-list-item/{$item->id}/checked", ['checked' => false])
            ->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_removing_a_member_tells_them(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/grocery-list/unshare', ['groceryListId' => $this->list->id, 'userId' => $this->member->id])
            ->assertOk();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->member->id
            && $job->type === 'list_removed'
            && $job->replace === ['user' => 'Tom', 'list' => 'Weekend']);
    }

    public function test_withdrawing_a_pending_invite_sends_nothing(): void
    {
        $invited = User::factory()->create();
        GroceryListInvites::create([
            'grocery_list_id' => $this->list->id,
            'user_id' => $invited->id,
            'email' => $invited->email,
            'status' => GroceryListInvitesStatus::PENDING,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/grocery-list/unshare', ['groceryListId' => $this->list->id, 'userId' => $invited->id])
            ->assertOk();

        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_deleting_a_list_tells_the_members(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/grocery-list/{$this->list->id}/delete")
            ->assertOk();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->member->id
            && $job->type === 'list_deleted'
            && $job->replace === ['user' => 'Tom', 'list' => 'Weekend']);
        Queue::assertNotPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->owner->id);
    }

    public function test_counted_texts_use_singular_and_plural(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        PushToken::create(['user_id' => $this->member->id, 'token' => 'ExponentPushToken[sam]']);

        (new SendPushNotification($this->member->id, 'items_added', ['user' => 'Tom', 'list' => 'Weekend', 'count' => 1]))->handle();
        (new SendPushNotification($this->member->id, 'items_added', ['user' => 'Tom', 'list' => 'Weekend', 'count' => 5]))->handle();

        $bodies = collect(Http::recorded())->map(fn ($pair) => json_decode($pair[0]->body(), true)[0]['body'])->all();
        $this->assertSame(['Tom heeft 1 product toegevoegd.', 'Tom heeft 5 producten toegevoegd.'], $bodies);
        Http::assertSent(fn (Request $request) => json_decode($request->body(), true)[0]['title'] === 'Nieuw op "Weekend"');
    }

    /** @return list<GroceryListItem> */
    private function createItems(array $names): array
    {
        return array_map(fn ($name) => GroceryListItem::create([
            'name' => $name,
            'quantity' => 1,
            'list_id' => $this->list->id,
            'checked' => false,
        ]), $names);
    }

    private function check(User $user, GroceryListItem $item): void
    {
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/grocery-list-item/{$item->id}/checked", ['checked' => true])
            ->assertOk();
    }

    // Runs the delayed group jobs now, as the queue worker would after two minutes
    private function runGroupJobs(): void
    {
        Queue::pushed(SendListActivityPush::class)->each(fn (SendListActivityPush $job) => $job->handle());
    }
}
