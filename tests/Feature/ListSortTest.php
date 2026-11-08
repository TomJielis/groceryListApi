<?php

namespace Tests\Feature;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSortTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function list(string $name, int $openItems = 0): GroceryList
    {
        $list = GroceryList::create(['name' => $name, 'created_by' => $this->user->id]);
        for ($i = 0; $i < $openItems; $i++) {
            GroceryListItem::create(['name' => "Item {$i}", 'quantity' => 1, 'list_id' => $list->id, 'checked' => false]);
        }

        return $list;
    }

    /** @return list<string> */
    private function order(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->user, 'sanctum')
            ->getJson('/api/grocery-list/index')
            ->assertOk()
            ->json('data.*.name');
    }

    public function test_the_list_with_the_most_open_items_is_on_top_and_ties_stay_oldest_first(): void
    {
        $this->list('Twee', 2);
        $this->list('Leeg');
        $this->list('Vijf', 5);
        $this->list('Ook twee', 2);

        $this->assertSame(['Vijf', 'Twee', 'Ook twee', 'Leeg'], $this->order());
    }

    public function test_only_what_is_still_open_right_now_counts(): void
    {
        $versions = $this->list('Veel versies');
        foreach (['Melk', 'Brood', 'Kaas'] as $name) {
            GroceryListItem::create(['name' => $name, 'quantity' => 1, 'list_id' => $versions->id, 'checked' => false]);
            GroceryListItem::create(['name' => $name, 'quantity' => 1, 'list_id' => $versions->id, 'checked' => true]);
        }
        $this->list('Eentje', 1);

        $this->assertSame(['Eentje', 'Veel versies'], $this->order());
    }

    public function test_the_favorite_is_on_top_even_with_nothing_open(): void
    {
        $this->list('Vijf', 5);
        $favorite = $this->list('Favoriet');
        $this->user->forceFill(['favorite_list_id' => $favorite->id])->save();

        $this->assertSame(['Favoriet', 'Vijf'], $this->order());
    }

    public function test_the_favorite_is_per_user_on_a_shared_list(): void
    {
        $member = User::factory()->create();
        $favorite = $this->list('Favoriet');
        $other = $this->list('Vijf', 5);
        foreach ([$favorite, $other] as $list) {
            GroceryListInvites::create(['grocery_list_id' => $list->id, 'user_id' => $member->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        }
        $this->user->forceFill(['favorite_list_id' => $favorite->id])->save();

        $this->assertSame(['Favoriet', 'Vijf'], $this->order());
        $this->assertSame(['Vijf', 'Favoriet'], $this->order($member));
    }
}
