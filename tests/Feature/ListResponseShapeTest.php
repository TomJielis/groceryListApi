<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// The exact fields the app and the website read from the list, item, week planner and card endpoints: a field added,
// renamed or dropped by accident fails here (GroceryListResource, GroceryListOverviewResource, GroceryListInviteResource,
// GroceryListItemResource, WeekPlannerProductResource, WeekPlannerSettingResource, CardResource)
class ListResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const LIST = ['id', 'name', 'created_by', 'created_at', 'updated_at'];

    private const INVITE = ['id', 'grocery_list_id', 'user_id', 'status', 'created_at', 'updated_at'];

    private const OVERVIEW = [
        'id', 'name', 'created_by' => ['id', 'name'], 'created_at', 'updated_at', 'open_items_count',
        'grocery_list_items_count', 'grocery_list_items_checked_count',
    ];

    // An item read from the database; one made just now has no checked yet
    private const ITEM = ['id', 'name', 'quantity', 'unit_price', 'list_id', 'checked', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const NEW_ITEM = ['id', 'name', 'quantity', 'unit_price', 'list_id', 'created_at', 'updated_at', 'created_by', 'updated_by'];

    private const PRODUCT = ['name', 'quantity', 'group', 'times_per_week', 'every_weeks', 'in_list', 'unit_price'];

    private const CARD = ['id', 'user_id', 'title', 'attachment', 'created_at', 'updated_at'];

    private User $owner;
    private User $member;
    private GroceryList $list;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->list = GroceryList::create(['name' => 'Boodschappen', 'created_by' => $this->owner->id]);
        GroceryListInvites::create(['grocery_list_id' => $this->list->id, 'user_id' => $this->member->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
    }

    private function item(string $name, bool $checked = false): GroceryListItem
    {
        return GroceryListItem::create(['name' => $name, 'quantity' => 1, 'list_id' => $this->list->id, 'checked' => $checked, 'unit_price' => 1.5]);
    }

    public function test_the_overview_shows_the_counts_and_who_joined(): void
    {
        $this->item('Melk');

        // The owner sees the members' addresses, a member only their names
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/grocery-list/index')->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => [...self::OVERVIEW, 'grocery_list_invites' => ['*' => [...self::INVITE, 'user' => ['id', 'name', 'email']]]]]]);
        $this->actingAs($this->member, 'sanctum')->getJson('/api/grocery-list/index')->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => [...self::OVERVIEW, 'grocery_list_invites' => ['*' => [...self::INVITE, 'user' => ['id', 'name']]]]]]);
    }

    public function test_a_list_made_or_renamed_answers_with_200(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $id = $this->postJson('/api/grocery-list/store', ['name' => 'Nieuw'])->assertOk()
            ->assertExactJsonStructure(['data' => self::LIST])->json('data.id');

        $this->postJson("/api/grocery-list/{$id}/update", ['name' => 'Anders'])->assertOk()
            ->assertExactJsonStructure(['data' => self::LIST])
            ->assertJsonPath('data.created_by', $this->owner->id);
    }

    public function test_the_list_messages_keep_their_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->postJson('/api/grocery-list/favorite', ['listId' => $this->list->id])->assertOk()->assertExactJsonStructure(['message']);
        $this->postJson('/api/grocery-list/unshare', [])->assertStatus(400)->assertExactJsonStructure(['message']);
        $this->postJson('/api/grocery-list/unshare', ['groceryListId' => $this->list->id, 'userId' => $this->member->id])
            ->assertOk()->assertExactJsonStructure(['message']);
        $this->deleteJson("/api/grocery-list/{$this->list->id}/delete")->assertOk()->assertExactJsonStructure(['message']);
    }

    public function test_the_items_keep_their_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $melk = $this->item('Melk');
        $this->item('Kaas', true);

        $this->postJson('/api/grocery-list-item/index', ['listId' => $this->list->id])->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => self::ITEM]]);
        $this->postJson('/api/grocery-list-item/store', ['name' => 'brood', 'list_id' => $this->list->id])->assertOk()
            ->assertExactJsonStructure(['data' => self::NEW_ITEM]);
        $this->postJson("/api/grocery-list-item/{$melk->id}/checked", ['checked' => true])->assertOk()
            ->assertExactJsonStructure(['data' => self::ITEM]);
        $this->postJson("/api/grocery-list-item/{$melk->id}/update", ['item' => ['quantity' => 3]])->assertOk()
            ->assertExactJsonStructure(['data' => self::ITEM]);
        $this->postJson("/api/grocery-list-item/{$melk->id}/increase")->assertOk()->assertExactJsonStructure(['data' => self::ITEM]);
        $this->postJson("/api/grocery-list-item/{$melk->id}/decrease", ['amount' => 100])->assertOk()->assertExactJsonStructure(['data' => self::ITEM]);
    }

    public function test_the_bulk_answers_keep_their_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $other = GroceryList::create(['name' => 'Ander', 'created_by' => $this->owner->id]);

        $this->postJson('/api/grocery-list-item/move', ['ids' => [$this->item('Melk')->id], 'list_id' => $other->id])
            ->assertOk()->assertExactJson(['moved' => 1]);
        $this->postJson('/api/grocery-list-item/delete-many', ['ids' => [$this->item('Kaas')->id]])
            ->assertOk()->assertExactJson(['deleted' => 1]);
        $this->deleteJson('/api/grocery-list-item/' . $this->item('Brood')->id . '/delete')->assertOk()->assertExactJsonStructure(['message']);
    }

    public function test_the_week_planner_keeps_its_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        foreach ([1, 2, 3] as $week) {
            $this->travelTo(Carbon::parse('2026-11-12 10:00:00')->subWeeks($week));
            $this->item('Melk', true);
        }
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
        $this->item('Kaas', true);

        $this->getJson("/api/grocery-list/{$this->list->id}/week-planner")->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => self::PRODUCT], 'weeks']);
        // Kaas is checked, so it comes back as a new item; Melk is nothing open, so the same
        $this->postJson("/api/grocery-list/{$this->list->id}/week-planner/add", ['items' => [['name' => 'Kaas', 'quantity' => 1]]])
            ->assertOk()->assertExactJsonStructure(['data' => ['*' => self::NEW_ITEM]]);
        $this->postJson("/api/grocery-list/{$this->list->id}/week-planner/add", ['items' => [['name' => 'Kaas', 'quantity' => 1]]])
            ->assertOk()->assertExactJsonStructure(['data' => ['*' => self::ITEM]]);
        $this->postJson('/api/user/week-planner', ['enabled' => false])->assertOk()->assertExactJson(['data' => ['week_planner' => false]]);
    }

    public function test_the_cards_keep_their_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->postJson('/api/cards/store', ['title' => 'Bonuskaart', 'attachment' => 'data:image/png;base64,AAAA'])->assertOk()
            ->assertExactJsonStructure(['success', 'data' => self::CARD]);
        $this->getJson('/api/cards/index')->assertOk()->assertExactJsonStructure(['success', 'data' => ['*' => self::CARD]]);
        $card = Card::first();
        $this->deleteJson("/api/cards/{$card->id}/delete")->assertOk()->assertExactJsonStructure(['success', 'message']);
    }
}
