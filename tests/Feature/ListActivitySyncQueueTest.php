<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * With QUEUE_CONNECTION=sync (e.g. locally) jobs run immediately and delays are ignored:
 * item pushes can't be grouped then, but must still be sent.
 */
class ListActivitySyncQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_pushes_are_still_sent_on_the_sync_queue(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);

        $owner = User::factory()->create(['name' => 'Tom', 'language' => 'en']);
        $member = User::factory()->create(['language' => 'en']);
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);
        GroceryListInvites::create([
            'grocery_list_id' => $list->id,
            'user_id' => $member->id,
            'email' => $member->email,
            'status' => GroceryListInvitesStatus::ACCEPTED,
        ]);
        PushToken::create(['user_id' => $member->id, 'token' => 'ExponentPushToken[member]']);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/grocery-list-item/store', ['name' => 'Melk', 'list_id' => $list->id])
            ->assertOk();

        Http::assertSent(fn (Request $request) => json_decode($request->body(), true)[0]['body'] === 'Tom added 1 item.');
    }
}
