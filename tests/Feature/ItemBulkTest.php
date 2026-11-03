<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// Several products at once: moved to the list of another shop, or deleted
class ItemBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private GroceryList $supermarket;
    private GroceryList $gamma;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->user = User::factory()->create();
        $this->supermarket = GroceryList::create(['name' => 'Supermarkt', 'created_by' => $this->user->id]);
        $this->gamma = GroceryList::create(['name' => 'Gamma', 'created_by' => $this->user->id]);
        $this->actingAs($this->user, 'sanctum');
    }

    private function item(GroceryList $list, string $name, int $quantity = 1, ?float $price = null, bool $checked = false): GroceryListItem
    {
        return GroceryListItem::create(['list_id' => $list->id, 'name' => $name, 'quantity' => $quantity, 'unit_price' => $price, 'checked' => $checked]);
    }

    // What a list shows: the latest row per name
    private function shown(GroceryList $list): array
    {
        return collect($this->postJson('/api/grocery-list-item/index', ['listId' => $list->id])->json('data'))
            ->mapWithKeys(fn (array $item) => [$item['name'] => [(int) $item['quantity'], (bool) $item['checked']]])
            ->sortKeys()
            ->all();
    }

    public function test_products_move_to_another_list_and_add_up_with_what_is_open_there(): void
    {
        $paint = $this->item($this->supermarket, 'Verf', 2, 12.5);
        $tape = $this->item($this->supermarket, 'Tape', 3);
        $milk = $this->item($this->supermarket, 'Melk');
        $this->item($this->gamma, 'Tape', 1);
        // Bought there before: a checked product doesn't get added to, it's put on the list again
        $this->item($this->gamma, 'Verf', 1, 10.0, true);

        $this->postJson('/api/grocery-list-item/move', ['ids' => [$paint->id, $tape->id], 'list_id' => $this->gamma->id])
            ->assertOk()->assertJsonPath('moved', 2);

        $this->assertSame(['Melk' => [1, false]], $this->shown($this->supermarket));
        $this->assertSame(['Tape' => [4, false], 'Verf' => [2, false]], $this->shown($this->gamma));
        // Its own price goes along
        $this->assertSame(12.5, (float) GroceryListItem::where('list_id', $this->gamma->id)->where('name', 'Verf')->latest('id')->value('unit_price'));
        $this->assertModelExists($milk);
    }

    public function test_only_from_and_to_lists_the_user_may_see(): void
    {
        $strangersList = GroceryList::create(['name' => 'Van iemand anders', 'created_by' => User::factory()->create()->id]);
        $theirs = $this->item($strangersList, 'Geheim');
        $mine = $this->item($this->supermarket, 'Verf');

        $this->postJson('/api/grocery-list-item/move', ['ids' => [$mine->id], 'list_id' => $strangersList->id])->assertForbidden();
        $this->postJson('/api/grocery-list-item/move', ['ids' => [$theirs->id, $mine->id], 'list_id' => $this->gamma->id])
            ->assertOk()->assertJsonPath('moved', 1);
        $this->assertModelExists($theirs);

        $this->postJson('/api/grocery-list-item/delete-many', ['ids' => [$theirs->id]])->assertOk()->assertJsonPath('deleted', 0);
        $this->assertModelExists($theirs);
        $this->postJson('/api/grocery-list-item/move', ['ids' => [], 'list_id' => $this->gamma->id])->assertUnprocessable();
    }

    public function test_several_products_are_deleted_at_once(): void
    {
        $paint = $this->item($this->supermarket, 'Verf');
        $tape = $this->item($this->supermarket, 'Tape');
        $this->item($this->supermarket, 'Melk');

        $this->postJson('/api/grocery-list-item/delete-many', ['ids' => [$paint->id, $tape->id]])
            ->assertOk()->assertJsonPath('deleted', 2);

        $this->assertSame(['Melk' => [1, false]], $this->shown($this->supermarket));
    }
}
