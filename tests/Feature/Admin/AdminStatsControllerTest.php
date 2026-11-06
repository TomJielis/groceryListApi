<?php

namespace Tests\Feature\Admin;

use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The admin dashboard's statistics: only for admins, and each with this and the previous month
 */
class AdminStatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINTS = ['users', 'items', 'lists', 'recipes', 'activity', 'versions', 'top-items'];

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->user = User::factory()->create();

        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->user->id]);
        GroceryListInvites::create(['grocery_list_id' => $list->id, 'user_id' => $this->admin->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 3, 'list_id' => $list->id, 'created_by' => $this->user->id]);
        GroceryListItem::create(['name' => 'Brood', 'quantity' => 1, 'list_id' => $list->id, 'created_by' => $this->user->id, 'checked' => true]);
    }

    private function stats(string $endpoint, string $query = '')
    {
        return $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/stats/{$endpoint}" . ($query ? "?{$query}" : ''));
    }

    public function test_only_admins_see_the_statistics(): void
    {
        foreach (self::ENDPOINTS as $endpoint) {
            // Without a login the admin check answers first (403), like for the other admin endpoints
            $this->assertContains($this->getJson("/api/admin/stats/{$endpoint}")->status(), [401, 403]);
            $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/stats/{$endpoint}")->assertForbidden();
        }
    }

    public function test_every_statistic_has_this_and_the_previous_month(): void
    {
        $month = now()->format('Y-m');
        $previous = now()->subMonthNoOverflow()->format('Y-m');

        foreach (self::ENDPOINTS as $endpoint) {
            $this->stats($endpoint)
                ->assertOk()
                ->assertJsonPath('current_month.period', $month)
                ->assertJsonPath('previous_month.period', $previous);
        }
    }

    public function test_users(): void
    {
        $this->stats('users')
            ->assertJsonPath('current_month.value', 2)
            ->assertJsonPath('current_month.breakdown.total', 2)
            ->assertJsonPath('current_month.breakdown.new_registrations', 2);
    }

    public function test_items(): void
    {
        $this->stats('items')
            ->assertJsonPath('current_month.value', 4)
            ->assertJsonPath('current_month.breakdown.added', 4)
            ->assertJsonPath('current_month.breakdown.checked', 1);
    }

    public function test_lists(): void
    {
        $this->stats('lists')
            ->assertJsonPath('current_month.value', 1)
            ->assertJsonPath('current_month.breakdown.created', 1)
            ->assertJsonPath('current_month.breakdown.shared', 1);
    }

    public function test_activity_has_the_days_and_the_busiest_lists(): void
    {
        $response = $this->stats('activity')->assertOk();

        $this->assertIsArray($response->json('current_month.daily'));
        $this->assertSame('Weekend', $response->json('current_month.top_lists.0.name'));
    }

    public function test_top_items(): void
    {
        $response = $this->stats('top-items')->assertOk();

        $this->assertSame('Melk', $response->json('current_month.most_added.0.name'));
        $this->assertSame(3, (int) $response->json('current_month.most_added.0.count'));
    }

    public function test_a_month_can_be_chosen(): void
    {
        $this->stats('items', 'month=2026-02')
            ->assertOk()
            ->assertJsonPath('current_month.period', '2026-02')
            ->assertJsonPath('previous_month.period', '2026-01')
            ->assertJsonPath('current_month.value', 0);
    }

    private function addRecipe(Cookbook $cookbook, User $by, string $title = 'Pasta'): Recipe
    {
        return Recipe::create(['cookbook_id' => $cookbook->id, 'created_by' => $by->id, 'title' => $title, 'categories' => ['main'], 'servings' => 4, 'prep_minutes' => 0, 'difficulty' => 'easy']);
    }

    public function test_recipes_and_cookbooks_this_month_and_in_total(): void
    {
        $own = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $this->user->id]);
        $shared = Cookbook::create(['name' => 'Samen', 'created_by' => $this->user->id]);
        CookbookInvite::create(['cookbook_id' => $shared->id, 'user_id' => $this->admin->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        $this->addRecipe($own, $this->user);
        $this->addRecipe($shared, $this->admin);
        // Last month's recipe counts in the total, not in this month
        $old = $this->addRecipe($own, $this->user, 'Oud');
        $old->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->save();

        $this->stats('recipes')->assertOk()
            ->assertJsonPath('current_month.value', 2)
            ->assertJsonPath('current_month.breakdown.added', 2)
            ->assertJsonPath('current_month.breakdown.cookbooks_created', 2)
            ->assertJsonPath('current_month.breakdown.total_recipes', 3)
            ->assertJsonPath('current_month.breakdown.total_cookbooks', 2)
            ->assertJsonPath('current_month.breakdown.shared_cookbooks', 1)
            ->assertJsonPath('previous_month.breakdown.added', 1);
    }

    public function test_a_users_details_show_their_cookbooks_and_recipes(): void
    {
        $own = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $this->user->id]);
        $theirs = Cookbook::create(['name' => 'Van de admin', 'created_by' => $this->admin->id]);
        CookbookInvite::create(['cookbook_id' => $theirs->id, 'user_id' => $this->user->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        $this->addRecipe($own, $this->user);
        $this->addRecipe($theirs, $this->user);
        $this->addRecipe($theirs, $this->admin);

        $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/users/{$this->user->id}")->assertOk()
            ->assertJsonPath('recipes.cookbooks_owned', 1)
            ->assertJsonPath('recipes.cookbooks_shared_with_user', 1)
            ->assertJsonPath('recipes.recipes_added', 2)
            ->assertJsonPath('recipes.recipes_access', 3)
            ->assertJsonPath('recipes.current_month.added', 2)
            ->assertJsonPath('recipes.current_month.cookbooks_created', 1)
            ->assertJsonPath('recipes.previous_month.added', 0);
        // Another month shows that month's numbers
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/users/{$this->user->id}?month=" . now()->subMonthsNoOverflow(2)->format('Y-m'))
            ->assertOk()->assertJsonPath('recipes.current_month.added', 0)->assertJsonPath('recipes.recipes_added', 2);
    }
}
