<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryListInvitesStatus;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// A recipe lives in one cookbook, whose people change it, and can be shown read-only in others: a couple's shared
// cookbook shown in a family cookbook, without the family changing the couple's recipes
class RecipeShownInTest extends TestCase
{
    use RefreshDatabase;

    private User $tom;
    private User $partner;
    private User $father;
    private Cookbook $together;
    private Cookbook $family;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Recipe::IMAGE_DISK);
        $this->tom = User::factory()->create();
        $this->partner = User::factory()->create();
        $this->father = User::factory()->create();

        $this->together = Cookbook::create(['name' => 'Samen koken', 'created_by' => $this->tom->id]);
        $this->join($this->together, $this->partner);
        $this->family = Cookbook::create(['name' => 'Familie', 'created_by' => $this->tom->id]);
        $this->join($this->family, $this->partner);
        $this->join($this->family, $this->father);

        $this->recipe = Recipe::create([
            'cookbook_id' => $this->together->id, 'created_by' => $this->tom->id, 'title' => 'Pasta pesto',
            'categories' => ['main'], 'servings' => 4, 'prep_minutes' => 10, 'difficulty' => 'easy',
        ]);
    }

    private function join(Cookbook $cookbook, User $user): void
    {
        CookbookInvite::create(['cookbook_id' => $cookbook->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => GroceryListInvitesStatus::ACCEPTED]);
    }

    private function payload(array $overrides = []): array
    {
        return ['cookbook_id' => $this->together->id, 'title' => 'Pasta pesto', 'categories' => ['main'], 'servings' => 4, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [], ...$overrides];
    }

    // Shows the recipe in the given cookbooks the way the form does: saved with the recipe
    private function showIn(User $user, array $cookbookIds)
    {
        return $this->actingAs($user, 'sanctum')->postJson("/api/recipes/{$this->recipe->id}/update", $this->payload(['shown_in' => $cookbookIds]));
    }

    public function test_a_recipe_shown_in_another_cookbook_is_read_only_there(): void
    {
        // Not shown yet: the father doesn't see it
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();

        $this->showIn($this->partner, [$this->family->id])->assertOk()->assertJsonPath('data.shown_in', [$this->family->id]);

        // The father sees it, read-only, with where it's from; favorite and the shopping list still work
        $this->actingAs($this->father, 'sanctum');
        $this->getJson("/api/recipes/{$this->recipe->id}")->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.cookbook_name', 'Samen koken')
            ->assertJsonPath('data.shown_elsewhere', []);
        $this->assertSame([$this->recipe->id], array_column($this->getJson('/api/recipes/index')->json('data'), 'id'));
        $this->postJson("/api/recipes/{$this->recipe->id}/favorite", ['favorite' => true])->assertOk();
        $this->postJson("/api/recipes/{$this->recipe->id}/update", $this->payload(['title' => 'Van vader']))->assertForbidden()
            ->assertJsonPath('message', 'Dit recept is van een ander kookboek; alleen wie daarin zit kan het aanpassen.');
        $this->deleteJson("/api/recipes/{$this->recipe->id}/delete")->assertForbidden();
        $this->postJson("/api/recipes/{$this->recipe->id}/image", ['image' => RecipeTest::photoDataUrl()])->assertForbidden();
        // Nor can he show it somewhere else
        $own = Cookbook::create(['name' => 'Van vader', 'created_by' => $this->father->id]);
        $this->showIn($this->father, [$this->family->id, $own->id])->assertForbidden();
        $this->assertSame('Pasta pesto', $this->recipe->fresh()->title);

        // The couple still change it, and the father sees the change: it's the same recipe
        $this->actingAs($this->partner, 'sanctum')->postJson("/api/recipes/{$this->recipe->id}/update", $this->payload(['title' => 'Pasta pesto met kip']))
            ->assertOk()->assertJsonPath('data.editable', true);
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertJsonPath('data.title', 'Pasta pesto met kip');
        $this->assertSame(1, Recipe::count());
        // Saving without shown_in (older apps) left it shown
        $this->assertSame([$this->family->id], $this->recipe->shownIn()->pluck('cookbooks.id')->all());
    }

    public function test_a_new_recipe_goes_into_its_cookbook_and_is_shown_in_others_at_once(): void
    {
        $id = $this->actingAs($this->tom, 'sanctum')->postJson('/api/recipes/store', $this->payload(['title' => 'Stamppot', 'shown_in' => [$this->family->id, $this->together->id]]))
            ->assertCreated()->assertJsonPath('data.shown_in', [$this->family->id])->json('data.id');
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipes/{$id}")->assertOk()->assertJsonPath('data.editable', false);

        // Only in cookbooks of the user's
        $stranger = Cookbook::create(['name' => 'Van een ander', 'created_by' => User::factory()->create()->id]);
        $this->actingAs($this->tom, 'sanctum')->postJson('/api/recipes/store', $this->payload(['shown_in' => [$stranger->id]]))->assertForbidden();
        $this->showIn($this->tom, [$stranger->id])->assertForbidden();
        $this->postJson('/api/recipes/store', $this->payload(['shown_in' => 'Familie']))->assertUnprocessable()->assertJsonValidationErrors('shown_in');
        $this->assertSame(2, Recipe::count());
    }

    public function test_the_cookbooks_count_shown_recipes_and_stop_showing_them(): void
    {
        $this->showIn($this->tom, [$this->family->id])->assertOk();
        // Showing it twice changes nothing
        $this->showIn($this->tom, [$this->family->id])->assertOk();

        $counts = collect($this->getJson('/api/cookbooks/index')->json('data'))->pluck('recipes_count', 'name');
        $this->assertSame(1, $counts['Familie']);
        $this->assertSame(1, $counts['Samen koken']);

        // The father can't take it out (he's not in its cookbook nor made Familie); a stranger doesn't even see it
        $this->actingAs($this->father, 'sanctum')->deleteJson("/api/recipes/{$this->recipe->id}/show-in/{$this->family->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(), 'sanctum')->deleteJson("/api/recipes/{$this->recipe->id}/show-in/{$this->family->id}")->assertNotFound();
        // Unticking it in the form takes it out
        $this->showIn($this->tom, [])->assertOk()->assertJsonPath('data.shown_in', []);
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();
        $this->assertNotNull(Recipe::find($this->recipe->id));
    }

    public function test_the_creator_of_the_cookbook_it_is_shown_in_takes_it_out(): void
    {
        // Mum's cookbook, which the partner is in: the partner shows the couple's recipe there
        $mum = User::factory()->create();
        $mums = Cookbook::create(['name' => 'Van mama', 'created_by' => $mum->id]);
        $this->join($mums, $this->partner);
        $this->showIn($this->partner, [$mums->id])->assertOk();

        // Tom isn't in Mum's cookbook, but sees where his recipe is shown and could take it out
        $this->actingAs($this->tom, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")
            ->assertJsonPath('data.shown_in', [])
            ->assertJsonPath('data.shown_elsewhere', [['id' => $mums->id, 'name' => 'Van mama']]);

        // Mum sees it read-only and takes it out of her cookbook
        $this->actingAs($mum, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertJsonPath('data.editable', false);
        $this->deleteJson("/api/recipes/{$this->recipe->id}/show-in/{$mums->id}")->assertOk();
        $this->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();
    }

    public function test_someone_who_leaves_takes_back_what_they_showed(): void
    {
        // The partner shows a recipe of Samen koken in a cookbook of their own, which a friend is in
        $friend = User::factory()->create();
        $theirs = Cookbook::create(['name' => 'Van de partner', 'created_by' => $this->partner->id]);
        $this->join($theirs, $friend);
        $this->showIn($this->partner, [$theirs->id, $this->family->id])->assertOk();
        $this->actingAs($friend, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertOk();

        // Tom shows another recipe of Samen koken in Familie himself
        $second = Recipe::create(['cookbook_id' => $this->together->id, 'created_by' => $this->tom->id, 'title' => 'Soep', 'categories' => ['main'], 'servings' => 2, 'prep_minutes' => 5, 'difficulty' => 'easy']);
        $second->shownIn()->attach($this->family->id, ['shown_by' => $this->tom->id]);

        // Tom removes the partner from Samen koken: what they showed of it is gone, what Tom showed stays
        $this->actingAs($this->tom, 'sanctum')->deleteJson("/api/cookbooks/{$this->together->id}/members/{$this->partner->id}")->assertOk();
        $this->actingAs($this->partner, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();
        $this->actingAs($friend, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();
        $this->getJson("/api/recipes/{$second->id}")->assertOk();
    }

    public function test_leaving_the_cookbook_it_is_shown_in_takes_it_out_too(): void
    {
        // The partner shows the couple's recipe in Familie, then leaves Familie: the family no longer sees it
        $this->showIn($this->partner, [$this->family->id])->assertOk();
        $this->actingAs($this->partner, 'sanctum')->deleteJson("/api/cookbooks/{$this->family->id}/delete")->assertOk();
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipes/{$this->recipe->id}")->assertNotFound();
        $this->assertDatabaseCount('cookbook_recipe', 0);
    }

    public function test_deleting_cookbooks_and_recipes(): void
    {
        $this->showIn($this->tom, [$this->family->id]);

        // Deleting the cookbook it's only shown in leaves the recipe in its own cookbook
        $this->deleteJson("/api/cookbooks/{$this->family->id}/delete")->assertOk();
        $this->assertNotNull(Recipe::find($this->recipe->id));
        $this->assertSame(0, $this->recipe->shownIn()->count());

        // Moving it into a cookbook it's shown in: it lives there now, not shown there as well
        $second = Cookbook::create(['name' => 'Tweede', 'created_by' => $this->tom->id]);
        $this->showIn($this->tom, [$second->id]);
        $this->postJson("/api/recipes/{$this->recipe->id}/update", $this->payload(['cookbook_id' => $second->id]))->assertOk();
        $this->assertSame([], $this->recipe->fresh()->shownIn()->pluck('cookbooks.id')->all());

        // Deleting the recipe takes it out of every cookbook
        $third = Cookbook::create(['name' => 'Derde', 'created_by' => $this->tom->id]);
        $this->postJson("/api/recipes/{$this->recipe->id}/update", $this->payload(['cookbook_id' => $second->id, 'shown_in' => [$third->id]]))->assertOk();
        $this->deleteJson("/api/recipes/{$this->recipe->id}/delete")->assertOk();
        $this->assertDatabaseCount('cookbook_recipe', 0);
    }

    public function test_the_creator_is_told_what_deleting_a_cookbook_takes_along(): void
    {
        $this->showIn($this->tom, [$this->family->id]);
        Recipe::create(['cookbook_id' => $this->together->id, 'created_by' => $this->tom->id, 'title' => 'Soep', 'categories' => ['main'], 'servings' => 2, 'prep_minutes' => 5, 'difficulty' => 'easy']);

        $together = collect($this->getJson('/api/cookbooks/index')->json('data'))->firstWhere('name', 'Samen koken');
        $this->assertSame(2, $together['own_recipes_count']);
        $this->assertSame(['recipes' => 1, 'cookbooks' => ['Familie']], $together['shown_elsewhere']);
        // Familie only shows it: nothing of its own goes elsewhere
        $family = collect($this->json('GET', '/api/cookbooks/index')->json('data'))->firstWhere('name', 'Familie');
        $this->assertSame(0, $family['own_recipes_count']);
        $this->assertSame(['recipes' => 0, 'cookbooks' => []], $family['shown_elsewhere']);

        // Only for the creator, who is the one deleting
        $together = collect($this->actingAs($this->partner, 'sanctum')->getJson('/api/cookbooks/index')->json('data'))->firstWhere('name', 'Samen koken');
        $this->assertNull($together['own_recipes_count']);
        $this->assertNull($together['shown_elsewhere']);
    }

    public function test_deleting_its_own_cookbook_takes_it_out_of_the_others_too(): void
    {
        // It's the creator's data: gone for the family as well
        $this->showIn($this->tom, [$this->family->id]);
        $this->deleteJson("/api/cookbooks/{$this->together->id}/delete")->assertOk();
        $this->assertNull(Recipe::find($this->recipe->id));
        $this->actingAs($this->father, 'sanctum')->getJson('/api/recipes/index')->assertJsonCount(0, 'data');
    }

    public function test_only_its_own_people_share_it_by_link_and_the_link_stops_when_they_leave(): void
    {
        $this->showIn($this->tom, [$this->family->id]);
        // The father sees it, but it's not his to hand out
        $this->actingAs($this->father, 'sanctum')->postJson("/api/recipes/{$this->recipe->id}/share-link")->assertForbidden()
            ->assertJsonPath('message', 'Alleen wie in het kookboek van dit recept zit, kan het delen.');

        // The partner's link stops working once they're out of Samen koken
        $token = basename($this->actingAs($this->partner, 'sanctum')->postJson("/api/recipes/{$this->recipe->id}/share-link")->assertOk()->json('data.url'));
        $this->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.status', 'valid');
        $this->actingAs($this->tom, 'sanctum')->deleteJson("/api/cookbooks/{$this->together->id}/members/{$this->partner->id}")->assertOk();
        $this->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.status', 'expired');
    }

    public function test_someone_it_is_only_shown_to_can_save_their_own_copy(): void
    {
        $this->showIn($this->tom, [$this->family->id]);
        $token = basename($this->actingAs($this->tom, 'sanctum')->postJson("/api/recipes/{$this->recipe->id}/share-link")->json('data.url'));
        $own = Cookbook::create(['name' => 'Van vader', 'created_by' => $this->father->id]);

        // Read-only for him, so not "already yours": he may save a copy he can change
        $this->actingAs($this->father, 'sanctum')->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.already', null);
        $copyId = $this->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])->assertCreated()->json('data.id');
        $this->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.already.type', 'copy')->assertJsonPath('data.already.recipe_id', $copyId);
    }

    public function test_several_recipes_are_shown_in_a_cookbook_at_once_and_only_the_users_own(): void
    {
        $second = Recipe::create([
            'cookbook_id' => $this->together->id, 'created_by' => $this->tom->id, 'title' => 'Lasagne',
            'categories' => ['main'], 'servings' => 4, 'prep_minutes' => 10, 'difficulty' => 'easy',
        ]);
        // Already in the family cookbook (its own), and one the partner may not change (only shown to them)
        $alreadyThere = Recipe::create([
            'cookbook_id' => $this->family->id, 'created_by' => $this->tom->id, 'title' => 'Stamppot',
            'categories' => ['main'], 'servings' => 4, 'prep_minutes' => 10, 'difficulty' => 'easy',
        ]);
        $strangers = Cookbook::create(['name' => 'Van iemand anders', 'created_by' => User::factory()->create()->id]);
        $notTheirs = Recipe::create([
            'cookbook_id' => $strangers->id, 'created_by' => $strangers->created_by, 'title' => 'Geheim',
            'categories' => ['main'], 'servings' => 4, 'prep_minutes' => 10, 'difficulty' => 'easy',
        ]);
        $ids = [$this->recipe->id, $second->id, $alreadyThere->id, $notTheirs->id];

        $this->actingAs($this->partner, 'sanctum')->postJson('/api/recipes/show-in', ['recipe_ids' => $ids, 'cookbook_id' => $this->family->id])
            ->assertOk()
            ->assertJsonPath('shown', 2)
            ->assertJsonPath('message', '2 recepten staan nu ook in "Familie".');
        $this->assertEqualsCanonicalizing([$this->recipe->id, $second->id], $this->family->shownRecipes()->pluck('recipes.id')->all());
        $this->assertSame($this->partner->id, (int) $this->family->shownRecipes()->first()->pivot->shown_by);
        $this->assertSame(0, $notTheirs->shownIn()->count());

        // Again: nothing twice
        $this->postJson('/api/recipes/show-in', ['recipe_ids' => $ids, 'cookbook_id' => $this->family->id])
            ->assertOk()->assertJsonPath('shown', 0);
        $this->assertSame(2, $this->family->shownRecipes()->count());

        // Only into a cookbook of theirs
        $this->postJson('/api/recipes/show-in', ['recipe_ids' => $ids, 'cookbook_id' => $strangers->id])->assertForbidden();
        $this->postJson('/api/recipes/show-in', ['recipe_ids' => [], 'cookbook_id' => $this->family->id])->assertUnprocessable();
    }

    public function test_several_recipes_are_deleted_at_once_but_only_the_users_own(): void
    {
        $second = Recipe::create([
            'cookbook_id' => $this->together->id, 'created_by' => $this->tom->id, 'title' => 'Lasagne',
            'categories' => ['main'], 'servings' => 4, 'prep_minutes' => 10, 'difficulty' => 'easy',
        ]);
        $this->showIn($this->tom, [$this->family->id])->assertOk();
        // Only shown to the father: he can't delete it
        $this->actingAs($this->father, 'sanctum')->postJson('/api/recipes/delete-many', ['recipe_ids' => [$this->recipe->id]])
            ->assertOk()->assertJsonPath('deleted', 0);
        $this->assertModelExists($this->recipe);

        $this->actingAs($this->partner, 'sanctum')->postJson('/api/recipes/delete-many', ['recipe_ids' => [$this->recipe->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 2)
            ->assertJsonPath('message', '2 recepten zijn verwijderd.');
        $this->assertSame(0, Recipe::count());
        $this->assertSame(0, $this->family->shownRecipes()->count());
    }
}
