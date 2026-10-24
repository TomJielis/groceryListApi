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
 * "My statistics": what the user added and checked off this month, on their own and shared lists
 */
class UserStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_what_the_user_added_and_checked_this_month(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $own = GroceryList::create(['name' => 'Eigen', 'created_by' => $user->id]);
        $shared = GroceryList::create(['name' => 'Gedeeld', 'created_by' => $friend->id]);
        GroceryListInvites::create(['grocery_list_id' => $shared->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        $elsewhere = GroceryList::create(['name' => 'Ander', 'created_by' => $friend->id]);

        GroceryListItem::create(['name' => 'melk', 'quantity' => 2, 'list_id' => $own->id, 'created_by' => $user->id]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $shared->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'checked' => true]);
        // Added by the friend, or on a list the user can't see: not theirs
        GroceryListItem::create(['name' => 'Kaas', 'quantity' => 5, 'list_id' => $shared->id, 'created_by' => $friend->id]);
        GroceryListItem::create(['name' => 'Jam', 'quantity' => 7, 'list_id' => $elsewhere->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/stats')->assertOk();

        $response->assertJsonPath('items.current_month.added', 3)
            ->assertJsonPath('items.current_month.checked', 1)
            ->assertJsonPath('items.previous_month.added', 0)
            ->assertJsonStructure([
                'items' => ['current_month' => ['added', 'checked', 'period'], 'previous_month'],
                'spend' => ['current_month', 'previous_month'],
                'user_breakdown',
                'top_items' => ['current_month' => ['most_added', 'most_checked']],
                'available_months',
                'invalid_login_attempts' => ['current_month', 'previous_month'],
            ]);
        // Names are counted together whatever their case
        $this->assertSame(['name' => 'Melk', 'count' => 3], $response->json('top_items.current_month.most_added.0'));
    }

    public function test_it_needs_a_login(): void
    {
        $this->getJson('/api/user/stats')->assertUnauthorized();
    }
}
