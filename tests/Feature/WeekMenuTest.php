<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use App\Models\WeekMenu;
use App\Models\WeekMenuEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WeekMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $partner;
    private Recipe $lasagne;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(Recipe::IMAGE_DISK);
        config(['app.url' => 'https://example.com']);
        $this->owner = User::factory()->create(['name' => 'Tom', 'language' => 'nl']);
        $this->partner = User::factory()->create(['name' => 'Sam', 'language' => 'en']);

        // Tom's own recipe, in his private cookbook: Sam can't see it
        $cookbook = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $this->owner->id]);
        $this->lasagne = Recipe::create([
            'cookbook_id' => $cookbook->id, 'created_by' => $this->owner->id, 'title' => 'Lasagne', 'categories' => ['main'],
            'servings' => 4, 'prep_minutes' => 20, 'difficulty' => 'easy',
        ]);
        $this->lasagne->replaceParts(
            [['name' => 'Ui', 'amount' => 2, 'unit' => 'st'], ['name' => 'Gehakt', 'amount' => 500, 'unit' => 'g']],
            ['Bak het gehakt.', 'Bak de lasagne in de oven.'],
        );
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/recipes/{$this->lasagne->id}/image", ['image' => RecipeTest::photoDataUrl()])->assertOk();
    }

    public function test_nobody_has_a_week_menu_until_they_make_one(): void
    {
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/week-menus/index')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/week-menus/store')
            ->assertCreated()
            ->assertJsonPath('data.name', 'Weekmenu')
            ->assertJsonPath('data.is_owner', true);
        $this->actingAs($this->partner, 'sanctum')->postJson('/api/week-menus/store', ['name' => 'Thuis'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Thuis');

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/week-menus/index')->assertJsonCount(1, 'data');
    }

    public function test_recipes_are_planned_per_day_with_what_putting_them_on_a_list_needs(): void
    {
        $menu = $this->menu();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-16', 'recipe_id' => $this->lasagne->id])
            ->assertCreated()
            // The recipe's own number of servings unless another is given
            ->assertJsonPath('data.servings', 4)
            ->assertJsonPath('data.added_by.name', 'Tom');
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-18', 'recipe_id' => $this->lasagne->id, 'servings' => 2])
            ->assertCreated();
        // Outside the week asked for
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-23', 'recipe_id' => $this->lasagne->id])
            ->assertCreated();

        $week = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-16&to=2026-11-22")
            ->assertOk()
            ->json('data');

        $this->assertSame(['2026-11-16', '2026-11-18'], array_column($week, 'date'));
        $this->assertSame([4, 2], array_column($week, 'servings'));
        $this->assertSame('Lasagne', $week[0]['recipe']['title']);
        $this->assertEquals([['name' => 'Ui', 'amount' => 2, 'unit' => 'st'], ['name' => 'Gehakt', 'amount' => 500, 'unit' => 'g']], $week[0]['recipe']['ingredients']);
        $this->assertNotNull($week[0]['recipe']['thumb_url']);
    }

    public function test_only_recipes_from_your_own_recipe_book_can_be_planned(): void
    {
        $menu = $this->menu($this->partner);

        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-16', 'recipe_id' => $this->lasagne->id])
            ->assertForbidden();
        $this->assertSame(0, WeekMenuEntry::count());
    }

    public function test_a_menu_you_are_not_in_cannot_be_seen_or_changed(): void
    {
        $menu = $this->menu();
        $entry = $this->plan($menu, '2026-11-16');

        $this->actingAs($this->partner, 'sanctum')->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-16&to=2026-11-22")->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/week-menus/{$menu->id}/entries/{$entry->id}/update", ['servings' => 8])->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/entries/{$entry->id}/delete")->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/week-menus/{$menu->id}/invite-link")->assertNotFound();
        $this->assertSame(4, $entry->fresh()->servings);
    }

    public function test_sharing_by_link_lets_the_other_plan_along_and_open_the_planned_recipe(): void
    {
        $menu = $this->menu();
        $this->plan($menu, '2026-11-16');
        $token = $this->inviteToken($menu);

        $this->actingAs($this->partner, 'sanctum')->getJson("/api/invite-links/{$token}?supports=week_menu")
            ->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.type', 'week_menu')
            ->assertJsonPath('data.name', 'Weekmenu')
            ->assertJsonPath('data.already_member', false);
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")
            ->assertOk()
            ->assertJsonPath('data.type', 'week_menu')
            ->assertJsonPath('data.id', $menu->id);
        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $push) => $push->userId === $this->owner->id
            && $push->type === 'week_menu_invite_accepted'
            // A screen every app version has
            && $push->data['url'] === '/recipes?view=week-menu');

        // Sam sees the menu and what's in it, and opens Tom's private recipe completely (to cook it), read-only
        $this->actingAs($this->partner, 'sanctum')->getJson('/api/week-menus/index')
            ->assertJsonPath('data.0.id', $menu->id)
            ->assertJsonPath('data.0.is_owner', false);
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-16&to=2026-11-22")
            ->assertJsonPath('data.0.recipe.title', 'Lasagne');
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/recipes/{$this->lasagne->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Lasagne')
            ->assertJsonPath('data.steps.1', 'Bak de lasagne in de oven.')
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.in_recipe_book', false)
            // The name of Tom's private cookbook stays his
            ->assertJsonPath('data.cookbook_name', null);
        $this->actingAs($this->partner, 'sanctum')->get("/api/recipes/{$this->lasagne->id}/image")->assertOk();
        // Changing or deleting it stays Tom's
        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/recipes/{$this->lasagne->id}/delete")->assertNotFound();
        $this->assertNotNull($this->lasagne->fresh());

        // But it isn't in Sam's recipe book
        $this->actingAs($this->partner, 'sanctum')->getJson('/api/recipes/index')->assertJsonCount(0, 'data');

        // Sam plans along, with a recipe of their own
        $sams = Recipe::create([
            'cookbook_id' => Cookbook::create(['name' => 'Sam', 'created_by' => $this->partner->id])->id,
            'created_by' => $this->partner->id, 'title' => 'Curry', 'categories' => ['main'], 'servings' => 2, 'difficulty' => 'easy',
        ]);
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-17', 'recipe_id' => $sams->id])
            ->assertCreated()
            ->assertJsonPath('data.added_by.name', 'Sam');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/recipes/{$sams->id}")->assertOk();
    }

    public function test_out_of_the_menu_the_recipe_is_private_again(): void
    {
        $menu = $this->menu();
        $entry = $this->plan($menu, '2026-11-16');
        $this->join($menu, $this->partner);
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/recipes/{$this->lasagne->id}")->assertOk();

        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/entries/{$entry->id}/delete")->assertOk();

        $this->actingAs($this->partner, 'sanctum')->getJson("/api/recipes/{$this->lasagne->id}")->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')->get("/api/recipes/{$this->lasagne->id}/image")->assertNotFound();
    }

    public function test_a_planned_recipe_can_be_saved_as_an_own_copy(): void
    {
        $menu = $this->menu();
        $this->plan($menu, '2026-11-16');
        $this->join($menu, $this->partner);
        $cookbook = Cookbook::create(['name' => 'Sam', 'created_by' => $this->partner->id]);

        $copyId = $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/recipes/{$this->lasagne->id}/copy", ['cookbook_id' => $cookbook->id])
            ->assertCreated()
            ->json('data.id');

        $copy = Recipe::with('ingredients')->findOrFail($copyId);
        $this->assertSame('Lasagne', $copy->title);
        $this->assertSame((int) $cookbook->id, (int) $copy->cookbook_id);
        $this->assertSame(['Ui', 'Gehakt'], $copy->ingredients->pluck('name')->all());
        $this->assertNotNull($copy->image_path);
        // In Sam's recipe book now; a second time makes no second copy
        $this->actingAs($this->partner, 'sanctum')->getJson('/api/recipes/index')->assertJsonCount(1, 'data');
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/recipes/{$this->lasagne->id}/copy", ['cookbook_id' => $cookbook->id])
            ->assertStatus(409);
    }

    public function test_an_older_app_does_not_get_week_menu_links(): void
    {
        $token = $this->inviteToken($this->menu());

        // Older apps don't send ?supports=week_menu: "not found", and they can't join
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/invite-links/{$token}")
            ->assertOk()
            ->assertExactJson(['data' => ['status' => 'not_found']]);
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept")->assertNotFound();

        $this->assertSame([$this->owner->id], WeekMenu::firstOrFail()->memberIds());
    }

    public function test_planned_recipes_move_to_another_day_or_number_of_servings(): void
    {
        $menu = $this->menu();
        $entry = $this->plan($menu, '2026-11-16');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/{$entry->id}/update", ['date' => '2026-11-19', 'servings' => 6])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-19')
            ->assertJsonPath('data.servings', 6);
    }

    public function test_only_the_creator_renames_removes_people_and_deletes_it(): void
    {
        $menu = $this->menu();
        $this->join($menu, $this->partner);

        $this->actingAs($this->partner, 'sanctum')->postJson("/api/week-menus/{$menu->id}/update", ['name' => 'Van Sam'])->assertForbidden();
        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/members/{$this->owner->id}")->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/week-menus/{$menu->id}/update", ['name' => 'Thuis'])->assertJsonPath('data.name', 'Thuis');
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/members/{$this->partner->id}")->assertJsonCount(0, 'data.members');
        $this->actingAs($this->partner, 'sanctum')->getJson('/api/week-menus/index')->assertJsonCount(0, 'data');
    }

    public function test_leaving_keeps_the_menu_and_deleting_removes_it_for_everyone(): void
    {
        $menu = $this->menu();
        $this->plan($menu, '2026-11-16');
        $this->join($menu, $this->partner);

        // Sam leaves: the menu stays for Tom
        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/delete")->assertOk();
        $this->assertNotNull($menu->fresh());
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/recipes/{$this->lasagne->id}")->assertNotFound();

        // Tom deletes it, with what's planned in it; the recipe itself stays
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/delete")->assertOk();
        $this->assertNull($menu->fresh());
        $this->assertSame(0, WeekMenuEntry::count());
        $this->assertNotNull($this->lasagne->fresh());
    }

    public function test_asking_for_too_long_a_stretch_is_refused(): void
    {
        $menu = $this->menu();

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/week-menus/{$menu->id}/entries?from=2026-01-01&to=2026-06-01")->assertUnprocessable();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-22&to=2026-11-16")->assertUnprocessable();
    }

    public function test_only_the_creator_makes_invite_links_and_removing_someone_stops_the_old_links(): void
    {
        $menu = $this->menu();
        $token = $this->inviteToken($menu);
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")->assertOk();

        // A member doesn't invite others
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/week-menus/{$menu->id}/invite-link")->assertForbidden();

        // Removed: the link they joined with no longer lets them back in
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/members/{$this->partner->id}")->assertOk();
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")->assertStatus(410);
        $this->assertSame([$this->owner->id], $menu->fresh()->memberIds());
        // The creator can't be removed from their own menu
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/members/{$this->owner->id}")->assertUnprocessable();
    }

    public function test_an_odd_supports_parameter_is_not_a_server_error(): void
    {
        $token = $this->inviteToken($this->menu());

        $this->getJson("/api/invite-links/{$token}?supports[]=week_menu")->assertOk()->assertJsonPath('data.status', 'not_found');
    }

    public function test_the_requests_are_checked_in_the_users_language(): void
    {
        $menu = $this->menu();

        // At most two months of days at once
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/week-menus/{$menu->id}/entries?from=2026-01-01&to=2026-03-15")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('week-menus.range_too_long', [], 'nl'))
            ->assertJsonValidationErrors(['to']);
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/week-menus/{$menu->id}/entries?from=2026-01-01&to=2026-03-01")
            ->assertOk();

        // A date that isn't one, and too many servings: said with the field's name
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '1-2-2026', 'recipe_id' => 1, 'servings' => 100])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'De datum moet een datum zijn (jjjj-mm-dd).')
            ->assertJsonPath('errors.servings.0', 'Het aantal personen mag hoogstens 99 zijn.');

        // A name is needed to rename it
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/update", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Vul de naam in.');
        $this->assertDatabaseCount('week_menu_entries', 0);
    }

    public function test_accepting_checks_what_is_brought_along(): void
    {
        $menu = $this->menu();
        $token = $this->inviteToken($menu);

        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/invite-links/{$token}/accept?supports=week_menu", ['bring_menu_id' => 'mine', 'bring_dishes' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bring_menu_id', 'bring_dishes']);
        // Nothing joined
        $this->assertSame([$this->owner->id], $menu->fresh()->memberIds());
    }

    private function recipeOf(User $user, string $title): Recipe
    {
        return Recipe::create([
            'cookbook_id' => Cookbook::create(['name' => $title, 'created_by' => $user->id])->id,
            'created_by' => $user->id, 'title' => $title, 'categories' => ['main'], 'servings' => 2, 'difficulty' => 'easy',
        ]);
    }

    public function test_when_the_planner_leaves_their_dishes_stay_and_can_still_be_opened(): void
    {
        $menu = $this->menu();
        $this->join($menu, $this->partner);
        $sams = $this->recipeOf($this->partner, 'Curry');
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-17', 'recipe_id' => $sams->id])->assertCreated();

        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/delete")->assertOk();

        // Sam could plan it, so it stays: Tom still sees and opens it (and can save an own copy)
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-16&to=2026-11-22")
            ->assertJsonPath('data.0.recipe.title', 'Curry');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/recipes/{$sams->id}")->assertOk();
        // Sam doesn't see the menu anymore
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-16&to=2026-11-22")
            ->assertNotFound();
    }

    public function test_the_dishes_put_on_a_list_get_a_check_with_who_which_list_and_for_how_many(): void
    {
        $menu = $this->menu();
        $monday = $this->plan($menu, '2026-11-16');
        $tuesday = $this->plan($menu, '2026-11-17');
        $list = \App\Models\GroceryList::create(['name' => 'Boodschappen', 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/added-to-list", ['entry_ids' => [$monday->id], 'list_id' => $list->id])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $week = collect($this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/week-menus/{$menu->id}/entries?from=2026-11-16&to=2026-11-22")->json('data'))->keyBy('id');
        $this->assertNotNull($week[$monday->id]['added_to_list_at']);
        $this->assertSame('Tom', $week[$monday->id]['added_to_list_by']['name']);
        $this->assertSame('Boodschappen', $week[$monday->id]['added_to_list']['name']);
        $this->assertSame(4, $week[$monday->id]['added_to_list_servings']);
        // Tuesday isn't on a list yet: the next "put on my list" takes only that one
        $this->assertNull($week[$tuesday->id]['added_to_list_at']);

        // More servings afterwards: the check stays, with the number it went on the list for
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/{$monday->id}/update", ['servings' => 6])
            ->assertJsonPath('data.servings', 6)
            ->assertJsonPath('data.added_to_list_servings', 4);

        // "Put on the list again": the check goes
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/{$monday->id}/update", ['reset_added' => true])
            ->assertJsonPath('data.added_to_list_at', null)
            ->assertJsonPath('data.added_to_list', null);
    }

    public function test_a_list_someone_else_cannot_see_is_not_named(): void
    {
        $menu = $this->menu();
        $entry = $this->plan($menu, '2026-11-16');
        $theirs = \App\Models\GroceryList::withoutGlobalScopes()->create(['name' => 'Geheim', 'created_by' => $this->partner->id]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/added-to-list", ['entry_ids' => [$entry->id], 'list_id' => $theirs->id])
            ->assertOk();

        $this->assertNull($entry->fresh()->added_to_list_id);
    }

    public function test_joining_with_an_own_menu_offers_bringing_its_dishes_along(): void
    {
        // Sam planned a curry in an own menu, then is invited to Tom's
        $samsMenu = $this->menu($this->partner);
        $curry = $this->recipeOf($this->partner, 'Curry');
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$samsMenu->id}/entries", ['date' => '2026-11-18', 'recipe_id' => $curry->id])->assertCreated();
        $tomsMenu = $this->menu();
        $token = $this->inviteToken($tomsMenu);

        $this->actingAs($this->partner, 'sanctum')->getJson("/api/invite-links/{$token}?supports=week_menu")
            ->assertJsonPath('data.own_menu.id', $samsMenu->id)
            ->assertJsonPath('data.own_menu.entries_count', 1);

        // "Bring them along": the curry moves to Tom's menu and Sam's own menu goes, so there's one
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu", ['bring_dishes' => true])->assertOk();

        $this->assertNull($samsMenu->fresh());
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/week-menus/{$tomsMenu->id}/entries?from=2026-11-16&to=2026-11-22")
            ->assertJsonPath('data.0.recipe.title', 'Curry');
        $this->actingAs($this->partner, 'sanctum')->getJson('/api/week-menus/index')->assertJsonCount(1, 'data');
    }

    public function test_joining_without_bringing_keeps_the_own_menu_and_an_empty_one_is_tidied_up(): void
    {
        $withDishes = $this->menu($this->partner);
        $curry = $this->recipeOf($this->partner, 'Curry');
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$withDishes->id}/entries", ['date' => '2026-11-18', 'recipe_id' => $curry->id])->assertCreated();
        $empty = $this->menu($this->partner);
        $token = $this->inviteToken($this->menu());

        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")->assertOk();

        // The one with dishes stays (not chosen to bring along), the empty one is gone
        $this->assertNotNull($withDishes->fresh());
        $this->assertNull($empty->fresh());
    }

    public function test_only_the_menu_the_screen_named_comes_along(): void
    {
        // Sam has two menus of their own with a dish each; the invite screen names the oldest
        $first = $this->menu($this->partner);
        $second = $this->menu($this->partner);
        $curry = $this->recipeOf($this->partner, 'Curry');
        foreach ([$first, $second] as $menu) {
            $this->actingAs($this->partner, 'sanctum')
                ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '2026-11-18', 'recipe_id' => $curry->id])->assertCreated();
        }
        $token = $this->inviteToken($this->menu());
        $named = $this->actingAs($this->partner, 'sanctum')->getJson("/api/invite-links/{$token}?supports=week_menu")->json('data.own_menu.id');
        $this->assertSame($first->id, $named);

        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/invite-links/{$token}/accept?supports=week_menu", ['bring_menu_id' => $named])->assertOk();

        // That one came along; the other one, not named, stays as it was
        $this->assertNull($first->fresh());
        $this->assertNotNull($second->fresh());
        $this->assertSame(1, $second->entries()->count());
    }

    public function test_an_empty_menu_with_a_link_out_is_kept_when_joining_another(): void
    {
        // Sam made an empty menu and shared its link (someone may be joining it), then joins Tom's
        $samsMenu = $this->menu($this->partner);
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/week-menus/{$samsMenu->id}/invite-link")->assertOk();
        $token = $this->inviteToken($this->menu());

        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")->assertOk();

        $this->assertNotNull($samsMenu->fresh());
    }

    public function test_accepting_twice_is_not_an_error(): void
    {
        $token = $this->inviteToken($this->menu());

        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")
            ->assertOk()->assertJsonPath('data.already_member', false);
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")
            ->assertOk()->assertJsonPath('data.already_member', true);
    }

    public function test_odd_dates_and_removing_someone_not_in_it_are_refused(): void
    {
        $menu = $this->menu();
        $token = $this->inviteToken($menu);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => '0001-01-01', 'recipe_id' => $this->lasagne->id])
            ->assertUnprocessable();
        // Not in the menu: 404, and the menu's link keeps working
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/members/{$this->partner->id}")->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/invite-links/{$token}?supports=week_menu")->assertJsonPath('data.status', 'valid');
    }

    public function test_bringing_a_menu_leaves_a_former_members_private_recipe_behind(): void
    {
        // Ann joined Sam's menu, planned her private soup and left again; Sam's curry is Sam's own
        $ann = User::factory()->create(['name' => 'Ann']);
        $samsMenu = $this->menu($this->partner);
        $samsToken = $this->actingAs($this->partner, 'sanctum')->postJson("/api/week-menus/{$samsMenu->id}/invite-link")->json('data.token');
        $this->actingAs($ann, 'sanctum')->postJson("/api/invite-links/{$samsToken}/accept?supports=week_menu")->assertOk();
        $soup = $this->recipeOf($ann, 'Soep');
        $this->actingAs($ann, 'sanctum')
            ->postJson("/api/week-menus/{$samsMenu->id}/entries", ['date' => '2026-11-17', 'recipe_id' => $soup->id])->assertCreated();
        $curry = $this->recipeOf($this->partner, 'Curry');
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$samsMenu->id}/entries", ['date' => '2026-11-18', 'recipe_id' => $curry->id])->assertCreated();
        $this->actingAs($ann, 'sanctum')->deleteJson("/api/week-menus/{$samsMenu->id}/delete")->assertOk();

        $tomsMenu = $this->menu();
        $token = $this->inviteToken($tomsMenu);
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/invite-links/{$token}?supports=week_menu")
            ->assertJsonPath('data.own_menu.entries_count', 1);
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/invite-links/{$token}/accept?supports=week_menu", ['bring_menu_id' => $samsMenu->id])->assertOk();

        // Only Sam's curry came along: Tom can't open Ann's soup, which she never shared with him
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/week-menus/{$tomsMenu->id}/entries?from=2026-11-16&to=2026-11-22")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipe.title', 'Curry');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/recipes/{$soup->id}")->assertNotFound();
    }

    public function test_two_people_putting_the_same_dishes_on_a_list_each_get_only_what_is_left(): void
    {
        $menu = $this->menu();
        $this->join($menu, $this->partner);
        $monday = $this->plan($menu, '2026-11-16');
        $tuesday = $this->plan($menu, '2026-11-17');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/added-to-list", ['entry_ids' => [$monday->id]])
            ->assertJsonPath('data.marked_ids', [$monday->id]);
        // Sam's sheet still had Monday: only Tuesday is Sam's, and Monday stays "by Tom"
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/added-to-list", ['entry_ids' => [$monday->id, $tuesday->id]])
            ->assertJsonPath('data.marked_ids', [$tuesday->id]);
        $this->assertSame($this->owner->id, $monday->fresh()->added_to_list_by);

        // Putting them on the list failed: Sam gives back only their own claim
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries/added-to-list", ['entry_ids' => [$monday->id, $tuesday->id], 'undo' => true])
            ->assertJsonPath('data.updated', 1);
        $this->assertNull($tuesday->fresh()->added_to_list_at);
        $this->assertNotNull($monday->fresh()->added_to_list_at);
    }

    public function test_dishes_of_another_menu_cannot_be_reached_through_your_own(): void
    {
        $mine = $this->menu($this->partner);
        $tomsEntry = $this->plan($this->menu(), '2026-11-16');

        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$mine->id}/entries/{$tomsEntry->id}/update", ['servings' => 9])->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')
            ->deleteJson("/api/week-menus/{$mine->id}/entries/{$tomsEntry->id}/delete")->assertNotFound();
        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/week-menus/{$mine->id}/entries/added-to-list", ['entry_ids' => [$tomsEntry->id]])
            ->assertJsonPath('data.marked_ids', []);
        $this->assertNull($tomsEntry->fresh()->added_to_list_at);
        $this->assertSame(4, $tomsEntry->fresh()->servings);
    }

    public function test_a_recipe_seen_through_a_menu_is_read_only_and_keeps_its_cookbook_to_itself(): void
    {
        $menu = $this->menu();
        $this->join($menu, $this->partner);
        $this->plan($menu, '2026-11-16');

        $this->actingAs($this->partner, 'sanctum')->getJson("/api/recipes/{$this->lasagne->id}")
            ->assertOk()
            ->assertJsonPath('data.in_recipe_book', false)
            ->assertJsonPath('data.cookbook_id', null)
            ->assertJsonPath('data.cookbook_name', null);
        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/recipes/{$this->lasagne->id}/delete")->assertNotFound();
        $this->assertNotNull($this->lasagne->fresh());
    }

    public function test_the_creator_cannot_remove_themselves(): void
    {
        $menu = $this->menu();

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/week-menus/{$menu->id}/members/{$this->owner->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('week-menus.owner_cannot_leave', [], 'nl'));
    }

    private function menu(?User $user = null): WeekMenu
    {
        $user ??= $this->owner;
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/week-menus/store')->assertCreated()->json('data.id');

        return WeekMenu::findOrFail($id);
    }

    private function plan(WeekMenu $menu, string $date): WeekMenuEntry
    {
        $id = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/week-menus/{$menu->id}/entries", ['date' => $date, 'recipe_id' => $this->lasagne->id])
            ->assertCreated()
            ->json('data.id');

        return WeekMenuEntry::findOrFail($id);
    }

    private function inviteToken(WeekMenu $menu): string
    {
        return $this->actingAs($this->owner, 'sanctum')->postJson("/api/week-menus/{$menu->id}/invite-link")->assertOk()->json('data.token');
    }

    private function join(WeekMenu $menu, User $user): void
    {
        $token = $this->inviteToken($menu);
        $this->actingAs($user, 'sanctum')->postJson("/api/invite-links/{$token}/accept?supports=week_menu")->assertOk();
    }
}
