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

// The exact fields the app and the website read from "My statistics", the push settings and the app version: a field
// added, renamed or dropped by accident fails here (UserStatsResource, NotificationSettingsResource, AppVersionResource)
class UserResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHS = ['current_month', 'previous_month'];

    private const RECIPES = [
        'cookbooks_owned', 'cookbooks_shared_with_user', 'recipes_added', 'recipes_access',
        'current_month' => ['added', 'cookbooks_created'], 'previous_month' => ['added', 'cookbooks_created'],
    ];

    private const SETTINGS = ['invites', 'list_changes', 'items_added', 'items_checked', 'list_completed'];

    public function test_my_statistics_are_unwrapped_with_both_months(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $list = GroceryList::create(['name' => 'Gedeeld', 'created_by' => $friend->id]);
        GroceryListInvites::create(['grocery_list_id' => $list->id, 'user_id' => $user->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 2, 'list_id' => $list->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'checked' => true, 'unit_price' => 1.5]);

        $month = ['added', 'checked', 'period'];
        $spend = ['total', 'by_list' => ['*' => ['id', 'name', 'total']]];
        $breakdown = ['*' => ['user_id', 'user_name', 'checked', 'added']];
        $this->actingAs($user, 'sanctum')->getJson('/api/user/stats')->assertOk()->assertExactJsonStructure([
            'items' => ['current_month' => $month, 'previous_month' => $month],
            'spend' => ['current_month' => $spend, 'previous_month' => ['total', 'by_list']],
            'user_breakdown' => ['current_month' => $breakdown, 'previous_month' => $breakdown],
            'top_items' => ['current_month' => [
                'most_added' => ['*' => ['name', 'count']],
                'most_checked' => ['*' => ['name', 'count']],
            ]],
            'recipes' => self::RECIPES,
            'available_months',
            'invalid_login_attempts' => self::MONTHS,
        ]);
    }

    public function test_the_push_settings_are_shown_and_saved_whole(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->getJson('/api/user/notification-settings')->assertOk()->assertExactJsonStructure(['data' => self::SETTINGS]);
        $this->postJson('/api/user/notification-settings', ['items_added' => false])
            ->assertOk()
            ->assertExactJsonStructure(['data' => self::SETTINGS]);
    }

    public function test_the_app_version_per_platform(): void
    {
        $platform = ['minimum_version', 'minimum_build', 'update_url'];

        $this->getJson('/api/app-version')
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['ios' => $platform, 'android' => $platform]]);
    }
}
