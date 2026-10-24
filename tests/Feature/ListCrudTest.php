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
 * Creating and renaming lists, and fetching the items of one list or of all the lists someone can see
 */
class ListCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private User $invited;
    private User $stranger;
    private GroceryList $list;

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
    }

    public function test_store_creates_a_list_owned_by_the_user(): void
    {
        $this->actingAs($this->stranger, 'sanctum')
            ->postJson('/api/grocery-list/store', ['name' => 'Feestje'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Feestje')
            ->assertJsonPath('data.created_by', $this->stranger->id);

        $this->assertDatabaseHas('grocery_lists', ['name' => 'Feestje', 'created_by' => $this->stranger->id]);
    }

    public function test_the_list_endpoints_need_a_login(): void
    {
        $this->postJson('/api/grocery-list/store', ['name' => 'Feestje'])->assertUnauthorized();
        $this->postJson("/api/grocery-list/{$this->list->id}/update", ['name' => 'Anders'])->assertUnauthorized();
        $this->postJson('/api/grocery-list-item/index', ['listId' => $this->list->id])->assertUnauthorized();
        $this->assertSame(1, GroceryList::withoutGlobalScopes()->count());
    }

    public function test_the_owner_renames_a_list(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/grocery-list/{$this->list->id}/update", ['name' => 'Zaterdag'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Zaterdag');

        $this->assertSame('Zaterdag', $this->list->fresh()->name);
    }

    public function test_a_rename_without_a_name_keeps_the_name(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/grocery-list/{$this->list->id}/update", [])
            ->assertOk();

        $this->assertSame('Weekend', $this->list->fresh()->name);
    }

    public function test_someone_without_access_cannot_rename_a_list(): void
    {
        foreach ([$this->stranger, $this->invited] as $user) {
            $status = $this->actingAs($user, 'sanctum')
                ->postJson("/api/grocery-list/{$this->list->id}/update", ['name' => 'Gekaapt'])
                ->status();
            $this->assertContains($status, [403, 404]);
        }

        $this->assertSame('Weekend', GroceryList::withoutGlobalScopes()->find($this->list->id)->name);
    }

    public function test_item_index_returns_the_items_of_one_list(): void
    {
        $other = GroceryList::create(['name' => 'Werk', 'created_by' => $this->owner->id]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 2, 'list_id' => $this->list->id]);
        GroceryListItem::create(['name' => 'Koffie', 'quantity' => 1, 'list_id' => $other->id]);

        foreach ([$this->owner, $this->member] as $user) {
            $names = collect($this->actingAs($user, 'sanctum')
                ->postJson('/api/grocery-list-item/index', ['listId' => $this->list->id])
                ->assertOk()
                ->json('data'))->pluck('name')->all();
            $this->assertSame(['Melk'], $names);
        }
    }

    public function test_item_index_without_a_list_returns_the_items_of_every_visible_list(): void
    {
        $own = GroceryList::create(['name' => 'Eigen', 'created_by' => $this->member->id]);
        $hidden = GroceryList::create(['name' => 'Van een ander', 'created_by' => $this->stranger->id]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $this->list->id]);
        GroceryListItem::create(['name' => 'Jam', 'quantity' => 1, 'list_id' => $own->id]);
        GroceryListItem::create(['name' => 'Geheim', 'quantity' => 1, 'list_id' => $hidden->id]);

        $names = collect($this->actingAs($this->member, 'sanctum')
            ->postJson('/api/grocery-list-item/index', ['listId' => null])
            ->assertOk()
            ->json('data'))->pluck('name')->sort()->values()->all();

        // Their own list and the shared one, not someone else's
        $this->assertSame(['Jam', 'Melk'], $names);
    }

    public function test_item_index_shows_nothing_of_a_list_without_access(): void
    {
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $this->list->id]);

        foreach ([$this->stranger, $this->invited] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/grocery-list-item/index', ['listId' => $this->list->id])
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }
}
