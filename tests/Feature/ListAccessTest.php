<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Lists and their items can only be changed by the owner and accepted members;
 * someone else (or someone whose invite is still pending) gets a 403/404 and nothing changes.
 */
class ListAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private User $invited;
    private User $stranger;
    private GroceryList $list;
    private GroceryListItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->invited = User::factory()->create();
        $this->stranger = User::factory()->create();
        $this->list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->owner->id]);
        GroceryListInvites::create(['grocery_list_id' => $this->list->id, 'user_id' => $this->member->id, 'email' => $this->member->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        GroceryListInvites::create(['grocery_list_id' => $this->list->id, 'user_id' => $this->invited->id, 'email' => $this->invited->email, 'status' => GroceryListInvitesStatus::PENDING]);
        $this->item = GroceryListItem::create(['name' => 'Melk', 'quantity' => 2, 'list_id' => $this->list->id, 'checked' => false]);
    }

    private function itemRequests(): array
    {
        $id = $this->item->id;
        return [
            ['postJson', "/api/grocery-list-item/{$id}/checked", ['checked' => true]],
            ['postJson', "/api/grocery-list-item/{$id}/update", ['item' => ['name' => 'Kaas', 'quantity' => 1, 'unit_price' => 5]]],
            ['postJson', "/api/grocery-list-item/{$id}/increase", ['amount' => 0]],
            ['postJson', "/api/grocery-list-item/{$id}/decrease", []],
            ['deleteJson', "/api/grocery-list-item/{$id}/delete", []],
            ['postJson', '/api/grocery-list-item/store', ['name' => 'Brood', 'list_id' => $this->list->id]],
        ];
    }

    public function test_a_stranger_cannot_touch_items_of_someone_elses_list(): void
    {
        foreach ([$this->stranger, $this->invited] as $user) {
            foreach ($this->itemRequests() as [$method, $url, $body]) {
                $this->actingAs($user, 'sanctum')->$method($url, $body)->assertForbidden();
            }
        }

        $this->item->refresh();
        $this->assertSame('Melk', $this->item->name);
        $this->assertSame(2, (int) $this->item->quantity);
        $this->assertFalse((bool) $this->item->checked);
        $this->assertSame(1, GroceryListItem::count());
    }

    public function test_the_owner_and_members_still_can(): void
    {
        foreach ([$this->owner, $this->member] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/grocery-list-item/{$this->item->id}/checked", ['checked' => true])
                ->assertOk();
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/grocery-list-item/store', ['name' => 'Brood', 'list_id' => $this->list->id])
                ->assertOk();
        }

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/grocery-list-item/{$this->item->id}/update", ['item' => ['name' => 'Melk', 'quantity' => 3, 'unit_price' => 1.5]])
            ->assertOk();
        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/grocery-list-item/{$this->item->id}/delete")
            ->assertOk();
    }

    public function test_an_item_needs_a_list(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/grocery-list-item/store', ['name' => 'Brood'])
            ->assertForbidden();
    }

    public function test_a_stranger_cannot_remove_people_from_a_list(): void
    {
        $this->actingAs($this->stranger, 'sanctum')
            ->postJson('/api/grocery-list/unshare', ['groceryListId' => $this->list->id, 'userId' => $this->member->id])
            ->assertNotFound();

        $this->assertDatabaseHas('grocery_list_invites', ['user_id' => $this->member->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
    }

    public function test_the_owner_and_members_can_still_remove_people(): void
    {
        // As in the app and the website: the owner removes someone, a member leaves
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/grocery-list/unshare', ['groceryListId' => $this->list->id, 'userId' => $this->invited->id])
            ->assertOk();
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/grocery-list/unshare', ['groceryListId' => $this->list->id, 'userId' => $this->member->id])
            ->assertOk();

        $this->assertDatabaseCount('grocery_list_invites', 0);
    }

    public function test_only_a_visible_list_can_be_the_favorite(): void
    {
        $this->actingAs($this->stranger, 'sanctum')
            ->postJson('/api/grocery-list/favorite', ['listId' => $this->list->id])
            ->assertNotFound();
        $this->assertNull($this->stranger->fresh()->favorite_list_id);

        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/grocery-list/favorite', ['listId' => $this->list->id])
            ->assertOk();
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/grocery-list/favorite', ['listId' => null])
            ->assertOk();
        $this->assertNull($this->member->fresh()->favorite_list_id);
    }
}
