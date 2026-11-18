<?php

namespace Tests\Feature\Admin;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// The exact fields the admin dashboard reads from each statistic, the users list and a user's page: a field added,
// renamed or dropped by accident fails here
class AdminStatsResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const COUNT_AND_PERCENTAGE = ['count', 'percentage'];

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true, 'accepted_terms_version' => '1.0'])->save();
        $this->user = User::factory()->create();
        $list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->user->id]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 2, 'list_id' => $list->id, 'created_by' => $this->user->id]);
        GroceryListItem::create(['name' => 'Brood', 'quantity' => 1, 'list_id' => $list->id, 'created_by' => $this->user->id, 'checked' => true, 'updated_by' => $this->user->id]);
        $this->actingAs($this->admin, 'sanctum');
    }

    // Both months with the same fields
    private function months(array $fields): array
    {
        return ['current_month' => ['period', ...$fields], 'previous_month' => ['period', ...$fields]];
    }

    public function test_the_month_statistics(): void
    {
        $withValue = fn (array $breakdown) => $this->months(['value', 'breakdown' => $breakdown]);

        $shapes = [
            'users' => $withValue(['total', 'new_registrations', 'active', 'verified_email', 'invalid_login_attempts']),
            'items' => $withValue(['added', 'checked', 'avg_per_user', 'avg_per_list']),
            'lists' => $withValue(['created', 'shared', 'avg_items_per_list', 'avg_members_per_shared_list']),
            'recipes' => $withValue(['added', 'cookbooks_created', 'total_recipes', 'total_cookbooks', 'shared_cookbooks']),
            'activity' => $this->months([
                'daily' => ['*' => ['date', 'items_added', 'items_checked']],
                'top_lists' => ['*' => ['id', 'name', 'items_added']],
            ]),
            'versions' => $this->months([
                'latest_version',
                'breakdown' => ['*' => self::COUNT_AND_PERCENTAGE],
                'on_latest' => self::COUNT_AND_PERCENTAGE,
            ]),
            'platforms' => $this->months([
                'active_users',
                'on_multiple_platforms',
                'breakdown' => ['website' => self::COUNT_AND_PERCENTAGE, 'ios' => self::COUNT_AND_PERCENTAGE, 'android' => self::COUNT_AND_PERCENTAGE],
            ]),
            'top-items' => $this->months([
                'most_added' => ['*' => ['name', 'count', 'lists_count']],
                'most_checked' => ['*' => ['name', 'count']],
            ]),
            'spend' => $this->months(['total']),
        ];

        foreach ($shapes as $endpoint => $shape) {
            $this->getJson("/api/admin/stats/{$endpoint}")->assertOk()->assertExactJsonStructure($shape);
        }
    }

    public function test_the_users_list(): void
    {
        $this->getJson('/api/admin/users')->assertOk()->assertExactJsonStructure([
            'users' => ['*' => ['id', 'name', 'email', 'created_at', 'email_verified', 'terms_version', 'last_active', 'lists_count', 'blocked']],
            'total',
        ]);
    }

    public function test_a_users_page(): void
    {
        $topItems = ['most_added' => ['*' => ['name', 'count', 'lists_count']], 'most_checked' => ['*' => ['name', 'count']]];
        $recipeMonth = ['added', 'cookbooks_created'];

        $this->getJson("/api/admin/users/{$this->user->id}")->assertOk()->assertExactJsonStructure([
            'user' => ['id', 'name', 'email', 'created_at', 'email_verified_at', 'terms_version', 'last_active', 'blocked'],
            'recent_logins',
            'lists' => ['owned', 'shared_with_user', 'total_access'],
            'recipes' => [
                'cookbooks_owned', 'cookbooks_shared_with_user', 'recipes_added', 'recipes_access',
                'current_month' => $recipeMonth, 'previous_month' => $recipeMonth,
            ],
            'items' => $this->months(['added', 'checked']),
            'top_items' => ['current_month' => $topItems, 'previous_month' => $topItems],
            'spend' => ['current_month' => ['total'], 'previous_month' => ['total']],
            'invalid_loggin_attempts' => ['current_month', 'previous_month'],
        ]);
    }
}
