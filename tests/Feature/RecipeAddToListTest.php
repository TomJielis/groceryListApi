<?php

namespace Tests\Feature;

use App\Jobs\SendListActivityPush;
use App\Models\Cookbook;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// A recipe's checked products go on a shopping list the way adding them by hand does
class RecipeAddToListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private GroceryList $list;
    private int $recipeId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user = User::factory()->create();
        $this->list = GroceryList::create(['name' => 'Weekboodschappen', 'created_by' => $this->user->id]);
        $cookbook = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $this->user->id]);
        $this->recipeId = $this->actingAs($this->user, 'sanctum')->postJson('/api/recipes/store', [
            'cookbook_id' => $cookbook->id,
            'title' => 'Pasta',
            'categories' => ['main'],
            'servings' => 4,
            'difficulty' => 'easy',
            'ingredients' => [['name' => 'Penne', 'amount' => 400, 'unit' => 'g']],
            'steps' => [],
        ])->assertCreated()->json('data.id');
    }

    private function add(array $items, ?int $listId = null)
    {
        return $this->postJson("/api/recipes/{$this->recipeId}/add-to-list", [
            'list_id' => $listId ?? $this->list->id,
            'items' => $items,
        ]);
    }

    // The current item of a name: its latest row
    private function current(string $name): ?GroceryListItem
    {
        return GroceryListItem::where('list_id', $this->list->id)->where('name', $name)->orderByDesc('id')->first();
    }

    public function test_new_products_are_added_with_their_number(): void
    {
        $response = $this->add([['name' => 'penne', 'quantity' => 1], ['name' => 'Eieren', 'quantity' => 4]])->assertOk();

        $this->assertSame(['Penne', 'Eieren'], array_column($response->json('data'), 'name'));
        $this->assertSame(1, (int) $this->current('Penne')->quantity);
        $this->assertSame(4, (int) $this->current('Eieren')->quantity);
        $this->assertFalse((bool) $this->current('Eieren')->checked);
    }

    public function test_a_product_still_open_on_the_list_gets_the_number_added(): void
    {
        $open = GroceryListItem::create(['name' => 'Eieren', 'quantity' => 2, 'list_id' => $this->list->id, 'unit_price' => 3.19]);

        // Matched on the name like adding it by hand (MySQL compares it case-insensitively, the test database doesn't)
        $this->add([['name' => 'Eieren', 'quantity' => 4]])->assertOk();

        $this->assertSame(6, (int) $open->fresh()->quantity);
        $this->assertSame(1, GroceryListItem::where('list_id', $this->list->id)->count());
    }

    public function test_a_checked_off_product_comes_back_as_a_new_item_with_its_last_price(): void
    {
        $checked = GroceryListItem::create(['name' => 'Penne', 'quantity' => 3, 'list_id' => $this->list->id, 'unit_price' => 1.29, 'checked' => true]);

        $this->add([['name' => 'Penne', 'quantity' => 1]])->assertOk();

        $current = $this->current('Penne');
        $this->assertNotSame($checked->id, $current->id);
        $this->assertSame(1, (int) $current->quantity);
        $this->assertFalse((bool) $current->checked);
        $this->assertEquals(1.29, $current->unit_price);
        // The checked one stays as it was (history)
        $this->assertSame(3, (int) $checked->fresh()->quantity);
    }

    public function test_the_others_on_the_list_hear_about_the_new_products(): void
    {
        GroceryListItem::create(['name' => 'Eieren', 'quantity' => 2, 'list_id' => $this->list->id]);

        $this->add([['name' => 'Eieren', 'quantity' => 4], ['name' => 'Penne', 'quantity' => 1], ['name' => 'Burrata', 'quantity' => 2]])->assertOk();

        // Only the two new products count, like adding by hand (a higher number of eggs sends nothing)
        Queue::assertPushed(SendListActivityPush::class, 2);
        Queue::assertPushed(SendListActivityPush::class, fn ($job) => $job->type === 'items_added' && $job->listId === $this->list->id);
    }

    public function test_a_shared_list_can_be_used(): void
    {
        $friend = User::factory()->create();
        $friendsList = GroceryList::create(['name' => 'Samen', 'created_by' => $friend->id]);
        GroceryListInvites::create(['grocery_list_id' => $friendsList->id, 'user_id' => $this->user->id, 'email' => $this->user->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);

        $this->add([['name' => 'Penne', 'quantity' => 1]], $friendsList->id)->assertOk();

        $this->assertSame(1, GroceryListItem::where('list_id', $friendsList->id)->count());
    }

    public function test_a_list_the_user_isnt_on_is_refused(): void
    {
        $stranger = User::factory()->create();
        $foreignList = GroceryList::create(['name' => 'Van iemand anders', 'created_by' => $stranger->id]);

        $this->add([['name' => 'Penne', 'quantity' => 1]], $foreignList->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'Je hebt geen toegang tot deze lijst.');
        $this->assertSame(0, GroceryListItem::count());
    }

    public function test_someone_elses_recipe_is_not_found(): void
    {
        $stranger = User::factory()->create();
        $strangersList = GroceryList::create(['name' => 'Eigen', 'created_by' => $stranger->id]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/recipes/{$this->recipeId}/add-to-list", ['list_id' => $strangersList->id, 'items' => [['name' => 'Penne', 'quantity' => 1]]])
            ->assertNotFound();
        $this->assertSame(0, GroceryListItem::count());
    }

    public function test_invalid_requests_are_refused(): void
    {
        $this->add([])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->add([['name' => 'Penne', 'quantity' => 0]])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->postJson("/api/recipes/{$this->recipeId}/add-to-list", ['items' => [['name' => 'Penne', 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('list_id');
        $this->assertSame(0, GroceryListItem::count());
        $this->assertNotNull(Recipe::find($this->recipeId));
    }
}
