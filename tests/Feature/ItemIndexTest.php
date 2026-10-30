<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The items endpoint: the newest item per name, only from lists the user may see (own and accepted shares)
class ItemIndexTest extends TestCase
{
    use RefreshDatabase;

    private function item(GroceryList $list, string $name, bool $checked = false): GroceryListItem
    {
        return GroceryListItem::create(['name' => $name, 'quantity' => 1, 'list_id' => $list->id, 'checked' => $checked]);
    }

    public function test_all_products_come_from_own_and_shared_lists_only(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $own = GroceryList::create(['name' => 'Own', 'created_by' => $user->id]);
        $shared = GroceryList::create(['name' => 'Shared', 'created_by' => $friend->id]);
        $pending = GroceryList::create(['name' => 'Pending', 'created_by' => $friend->id]);
        $foreign = GroceryList::create(['name' => 'Foreign', 'created_by' => $friend->id]);
        GroceryListInvites::create(['grocery_list_id' => $shared->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        GroceryListInvites::create(['grocery_list_id' => $pending->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => GroceryListInvitesStatus::PENDING]);

        $this->item($own, 'Melk', true);
        $newestMelk = $this->item($own, 'Melk');
        $this->item($shared, 'Kaas');
        $this->item($pending, 'Geheim');
        $this->item($foreign, 'Ook geheim');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/grocery-list-item/index', ['listId' => null])->assertOk();
        $names = collect($response->json('data'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['Kaas', 'Melk'], $names);
        $this->assertSame($newestMelk->id, collect($response->json('data'))->firstWhere('name', 'Melk')['id']);
    }

    public function test_an_edit_only_changes_the_fields_it_sends(): void
    {
        $user = User::factory()->create();
        $list = GroceryList::create(['name' => 'Own', 'created_by' => $user->id]);
        $item = GroceryListItem::create(['name' => 'Hummus', 'quantity' => 1, 'list_id' => $list->id, 'unit_price' => 1.5]);
        $update = fn (array $fields) => $this->actingAs($user, 'sanctum')
            ->postJson("/api/grocery-list-item/{$item->id}/update", ['item' => $fields])->assertOk();

        // Someone with an older copy (no price yet) changes only the quantity: the price stays
        $update(['quantity' => 2]);
        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertEquals(1.5, $item->fresh()->unit_price);

        // A price sent as null clears it
        $update(['unit_price' => null]);
        $this->assertNull($item->fresh()->unit_price);

        // The website sends every field, as before
        $update(['name' => 'hummus naturel', 'quantity' => 3, 'unit_price' => 2.25]);
        $fresh = $item->fresh();
        $this->assertSame('Hummus naturel', $fresh->name);
        $this->assertSame(3, (int) $fresh->quantity);
        $this->assertEquals(2.25, $fresh->unit_price);
    }

    public function test_one_list_gives_its_items_and_a_foreign_list_gives_nothing(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $own = GroceryList::create(['name' => 'Own', 'created_by' => $user->id]);
        $other = GroceryList::create(['name' => 'Other own', 'created_by' => $user->id]);
        $foreign = GroceryList::create(['name' => 'Foreign', 'created_by' => $friend->id]);
        $this->item($own, 'Brood');
        $this->item($other, 'Thee');
        $this->item($foreign, 'Geheim');

        $this->actingAs($user, 'sanctum')->postJson('/api/grocery-list-item/index', ['listId' => $own->id])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Brood');
        $this->actingAs($user, 'sanctum')->postJson('/api/grocery-list-item/index', ['listId' => $foreign->id])
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
