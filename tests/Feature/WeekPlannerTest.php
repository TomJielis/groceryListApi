<?php

namespace Tests\Feature;

use App\Jobs\SendListActivityPush;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeekPlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private GroceryList $list;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));

        $this->user = User::factory()->create(['name' => 'Tom']);
        $this->list = GroceryList::create(['name' => 'Weekend', 'created_by' => $this->user->id]);
    }

    public function test_products_of_nearly_every_week_are_week_groceries(): void
    {
        // 12 weeks: milk 3 times a week, tomatoes every week, washing powder every 4 weeks, cake once
        for ($week = 1; $week <= 12; $week++) {
            foreach ([0, 2, 4] as $day) {
                $this->addedInWeek('Melk', $week, $day);
            }
            $this->addedInWeek('Tomaten', $week, 1, 2);
            if ($week % 4 === 1) {
                $this->addedInWeek('Wasmiddel', $week, 3);
            }
        }
        $this->addedInWeek('Taart', 2, 5);

        $products = $this->planner();

        $this->assertSame(['Melk', 'Tomaten', 'Wasmiddel'], array_column($products, 'name'));
        $this->assertSame(['weekly', 'weekly', 'sometimes'], array_column($products, 'group'));
        // A week's worth: 3 times 1 milk is 3 milk; 2 tomatoes once a week is 2
        $this->assertSame([3, 2, 1], array_column($products, 'quantity'));
        $this->assertSame(3.0, (float) $products[0]['times_per_week']);
        $this->assertNull($products[0]['every_weeks']);
        $this->assertSame(4, $products[2]['every_weeks']);
    }

    public function test_quantities_follow_what_you_buy_now(): void
    {
        // Kip: once every 2 or 3 weeks at first, then twice a week for the last 4 weeks (like the real list)
        foreach ([12, 9, 8, 6] as $week) {
            $this->addedInWeek('Kip', $week, 2);
        }
        foreach ([4, 3, 2, 1] as $week) {
            $this->addedInWeek('Kip', $week, 0);
            $this->addedInWeek('Kip', $week, 3);
        }
        // Something else every week, so all 12 weeks were used
        for ($week = 1; $week <= 12; $week++) {
            $this->addedInWeek('Brood', $week, 1);
        }

        $kip = collect($this->planner())->firstWhere('name', 'Kip');

        $this->assertSame('weekly', $kip['group']);
        $this->assertSame(2, $kip['quantity']);
        $this->assertSame(2.0, (float) $kip['times_per_week']);
    }

    public function test_weeks_without_any_shopping_dont_count(): void
    {
        // The list was used in 6 of the 12 weeks (a holiday in between); milk was there every time
        foreach ([1, 2, 3, 9, 10, 11] as $week) {
            $this->addedInWeek('Melk', $week, 1);
        }

        $melk = collect($this->planner())->firstWhere('name', 'Melk');

        $this->assertSame('weekly', $melk['group']);
        $this->assertSame(1, $melk['quantity']);
    }

    public function test_weeks_run_monday_to_sunday_and_the_current_week_doesnt_count(): void
    {
        // Sunday 23:30 and Monday 00:30 in Dutch time are in different weeks (22:30 and 23:30 UTC in winter)
        $lastMonday = CarbonImmutable::parse('2026-11-02 00:00', 'Europe/Amsterdam');
        $this->addedAt('Melk', $lastMonday->subMinutes(30));
        $this->addedAt('Melk', $lastMonday->addMinutes(30));
        // The current week (Monday 9 to now, Saturday 14 November) is still going: it isn't counted
        $this->addedInWeek('Melk', 0, 1);
        $this->addedInWeek('Melk', 0, 4);

        $melk = collect($this->planner())->firstWhere('name', 'Melk');

        // 2 weeks with milk, once each: weekly, 1 a week (not 2 or 4 because of the current week)
        $this->assertSame('weekly', $melk['group']);
        $this->assertSame(1, $melk['quantity']);
        $this->assertSame(1.0, (float) $melk['times_per_week']);
    }

    public function test_weeks_follow_the_phones_time_zone(): void
    {
        // Monday 06:00 in Tokyo is still Sunday evening in the Netherlands: last week there, this week in Tokyo
        $this->addedAt('Melk', CarbonImmutable::parse('2026-11-09 06:00', 'Asia/Tokyo'));
        $this->addedInWeek('Melk', 2, 2);

        $inTokyo = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/grocery-list/{$this->list->id}/week-planner?timezone=Asia/Tokyo")
            ->assertOk()
            ->json('data');
        $inAmsterdam = $this->planner();

        // In Tokyo the Monday one is in the current week (not counted): milk in 1 week only, so nothing
        $this->assertSame([], $inTokyo);
        $this->assertSame('Melk', $inAmsterdam[0]['name']);
    }

    public function test_an_unknown_time_zone_is_refused(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/grocery-list/{$this->list->id}/week-planner?timezone=Mars/Olympus")
            ->assertUnprocessable();
    }

    public function test_a_young_list_counts_only_the_weeks_it_existed(): void
    {
        foreach ([1, 2, 3] as $week) {
            $this->addedInWeek('Brood', $week, 4);
        }

        $products = $this->planner();

        $this->assertSame('Brood', $products[0]['name']);
        $this->assertSame('weekly', $products[0]['group']);
    }

    public function test_history_older_than_twelve_weeks_is_ignored(): void
    {
        foreach ([13, 14, 15, 16] as $week) {
            $this->addedInWeek('Oude gewoonte', $week, 2);
        }

        $this->assertSame([], $this->planner());
    }

    public function test_a_product_open_on_the_list_is_marked(): void
    {
        foreach ([1, 2, 3] as $week) {
            $this->addedInWeek('Melk', $week, 1);
        }
        foreach ([1, 2] as $week) {
            $this->addedInWeek('Kaas', $week, 2);
        }
        // Kaas is on the list right now (added this week, not checked off)
        $this->addedInWeek('Kaas', 0, 1, 1, false);

        $products = collect($this->planner())->keyBy('name');

        $this->assertFalse($products['Melk']['in_list']);
        $this->assertTrue($products['Kaas']['in_list']);
    }

    public function test_only_lists_the_user_may_see(): void
    {
        $other = User::factory()->create();
        $theirs = GroceryList::create(['name' => 'Theirs', 'created_by' => $other->id]);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/grocery-list/{$theirs->id}/week-planner")->assertForbidden();
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/grocery-list/{$theirs->id}/week-planner/add", ['items' => [['name' => 'Melk', 'quantity' => 1]]])
            ->assertForbidden();
    }

    public function test_no_access_is_told_in_the_users_language(): void
    {
        $theirs = GroceryList::create(['name' => 'Theirs', 'created_by' => User::factory()->create()->id]);
        $english = User::factory()->create(['language' => 'en']);
        $dutch = User::factory()->create(['language' => 'nl']);

        $this->actingAs($english, 'sanctum')->getJson("/api/grocery-list/{$theirs->id}/week-planner")
            ->assertForbidden()->assertJsonPath('message', 'You don\'t have access to this list.');
        $this->actingAs($dutch, 'sanctum')->postJson('/api/grocery-list-item/store', ['name' => 'Melk', 'list_id' => $theirs->id])
            ->assertForbidden()->assertJsonPath('message', 'Je hebt geen toegang tot deze lijst.');
    }

    public function test_a_shared_list_counts_what_every_member_adds(): void
    {
        $member = User::factory()->create();
        GroceryListInvites::create([
            'grocery_list_id' => $this->list->id,
            'user_id' => $member->id,
            'status' => GroceryListInvitesStatus::ACCEPTED,
        ]);
        foreach ([1, 2, 3] as $week) {
            $this->addedInWeek('Melk', $week, 1);
        }

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/grocery-list/{$this->list->id}/week-planner")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Melk');
    }

    public function test_ticked_products_are_added_in_one_go(): void
    {
        // Melk is still open with 1: it gets 3 more; Brood is new and keeps last time's price
        GroceryListItem::create(['name' => 'Melk', 'quantity' => 1, 'list_id' => $this->list->id, 'checked' => false]);
        GroceryListItem::create(['name' => 'Brood', 'quantity' => 1, 'list_id' => $this->list->id, 'checked' => true, 'unit_price' => 2.5]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/grocery-list/{$this->list->id}/week-planner/add", ['items' => [
                ['name' => 'Melk', 'quantity' => 3],
                ['name' => 'Brood', 'quantity' => 1],
            ]])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $latest = fn (string $name) => GroceryListItem::where('list_id', $this->list->id)->where('name', $name)->latest('id')->first();
        $this->assertSame(4, (int) $latest('Melk')->quantity);
        $this->assertFalse((bool) $latest('Brood')->checked);
        $this->assertSame(2.5, (float) $latest('Brood')->unit_price);
        // One grouped push for the other members, not one per product
        Queue::assertPushed(SendListActivityPush::class, 1);
    }

    public function test_adding_needs_valid_products(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/grocery-list/{$this->list->id}/week-planner/add", ['items' => [['name' => '', 'quantity' => 0]]])
            ->assertUnprocessable();
    }

    public function test_the_planner_is_on_by_default_and_can_be_switched_off(): void
    {
        // The column default (not the model): a register request sends no week_planner, so it works before and
        // after the migration ran on a server
        $this->actingAs($this->user->fresh(), 'sanctum')->postJson('/api/me')->assertJsonPath('user.week_planner', true);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/week-planner', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.week_planner', false);

        $this->assertFalse($this->user->fresh()->week_planner);
    }

    private function planner(): array
    {
        return $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/grocery-list/{$this->list->id}/week-planner")
            ->assertOk()
            ->json('data');
    }

    // A row as if the product was added in a calendar week: 0 = this week, 1 = last week (Monday to Sunday), ...;
    // $day 0 = Monday ... 6 = Sunday, at noon Dutch time
    private function addedInWeek(string $name, int $weeksAgo, int $day, int $quantity = 1, bool $checked = true): void
    {
        $monday = now()->toImmutable()->setTimezone('Europe/Amsterdam')->startOfWeek(CarbonImmutable::MONDAY);
        $this->addedAt($name, $monday->subWeeks($weeksAgo)->addDays($day)->setTime(12, 0), $quantity, $checked);
    }

    // Items are versioned: every addition is a row
    private function addedAt(string $name, CarbonImmutable $at, int $quantity = 1, bool $checked = true): void
    {
        $item = GroceryListItem::create([
            'name' => $name,
            'quantity' => $quantity,
            'list_id' => $this->list->id,
            'checked' => $checked,
        ]);
        $item->created_at = $at->utc();
        $item->save();
    }
}
