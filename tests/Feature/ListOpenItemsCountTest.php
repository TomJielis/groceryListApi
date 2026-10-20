<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListOpenItemsCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_items_count_only_counts_the_latest_version_of_each_item(): void
    {
        $owner = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $owner->id]);

        // Melk: added, checked off, added again -> latest version is open
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $list->id, 'checked' => true]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $list->id, 'checked' => false]);
        // Brood: an older open version and a newer checked one -> done
        GroceryListItem::create(['name' => 'Brood', 'quantity' => 1, 'list_id' => $list->id, 'checked' => false]);
        GroceryListItem::create(['name' => 'Brood', 'quantity' => 1, 'list_id' => $list->id, 'checked' => true]);
        // Kaas: open
        GroceryListItem::create(['name' => 'Kaas', 'quantity' => 1, 'list_id' => $list->id, 'checked' => false]);

        $list = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/grocery-list/index')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(2, (int) $list['open_items_count']);
        // The counts the web app uses are unchanged: every version
        $this->assertSame(5, $list['grocery_list_items_count']);
        $this->assertSame(2, $list['grocery_list_items_checked_count']);
    }
}
