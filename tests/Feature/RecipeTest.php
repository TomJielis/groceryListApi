<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryListInvitesStatus;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Recipes live in cookbooks: members see and edit them, everyone else gets a 404. Favorites are personal.
class RecipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Recipe::IMAGE_DISK);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Pasta pesto',
            'description' => 'Snel en makkelijk',
            'category' => 'main',
            'servings' => 4,
            'prep_minutes' => 10,
            'cook_minutes' => 15,
            'oven_minutes' => null,
            'oven_celsius' => 200,
            'difficulty' => 'easy',
            'kcal' => 640,
            'source_url' => 'https://www.ah.nl/allerhande/recept/R-R123/pasta-pesto',
            'ingredients' => [
                ['name' => 'Penne', 'amount' => 400, 'unit' => 'g'],
                ['name' => 'Pesto', 'amount' => 1, 'unit' => 'pot'],
                ['name' => 'Peper en zout', 'amount' => null, 'unit' => ''],
            ],
            'steps' => ['Kook de pasta.', 'Meng met de pesto.'],
            ...$overrides,
        ];
    }

    // The user's own cookbook, made the first time it's needed (in the app the user makes it)
    private function cookbookOf(User $user): Cookbook
    {
        return Cookbook::firstOrCreate(['created_by' => $user->id], ['name' => 'Mijn recepten']);
    }

    // Into the user's own cookbook, unless the overrides say otherwise
    private function createRecipe(User $user, array $overrides = []): int
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/recipes/store', $this->payload(['cookbook_id' => $this->cookbookOf($user)->id, ...$overrides]))
            ->assertCreated()->json('data.id');
    }

    // A small photo as a data URL, the way the app sends it
    public static function photoDataUrl(int $width = 40, int $height = 20): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    private function share(Cookbook $cookbook, User $member): void
    {
        CookbookInvite::create([
            'cookbook_id' => $cookbook->id,
            'user_id' => $member->id,
            'email' => $member->email,
            'status' => GroceryListInvitesStatus::ACCEPTED,
        ]);
    }

    public function test_a_recipe_is_saved_with_its_ingredients_and_steps_in_order(): void
    {
        $user = User::factory()->create();
        $id = $this->createRecipe($user);

        $recipe = $this->actingAs($user, 'sanctum')->getJson("/api/recipes/{$id}")->assertOk()->json('data');
        $this->assertSame('Pasta pesto', $recipe['title']);
        $this->assertSame(['Penne', 'Pesto', 'Peper en zout'], array_column($recipe['ingredients'], 'name'));
        $this->assertEquals(400, $recipe['ingredients'][0]['amount']);
        $this->assertNull($recipe['ingredients'][2]['amount']);
        $this->assertSame(['Kook de pasta.', 'Meng met de pesto.'], $recipe['steps']);
        $this->assertSame($user->name, $recipe['created_by']['name']);
        $this->assertFalse($recipe['favorite']);

        $this->assertSame($this->cookbookOf($user)->id, Recipe::find($id)->cookbook_id);
    }

    public function test_the_overview_lists_own_recipes_a_to_z_without_steps(): void
    {
        $user = User::factory()->create();
        $this->createRecipe($user, ['title' => 'Tweede']);
        $this->createRecipe($user, ['title' => 'Appeltaart']);
        $this->createRecipe($user, ['title' => 'Eerste']);
        $this->createRecipe(User::factory()->create(), ['title' => 'Van iemand anders']);

        $recipes = $this->actingAs($user, 'sanctum')->getJson('/api/recipes/index')->assertOk()->json('data');
        // A to Z
        $this->assertSame(['Appeltaart', 'Eerste', 'Tweede'], array_column($recipes, 'title'));
        $this->assertSame(['Penne', 'Pesto', 'Peper en zout'], $recipes[0]['ingredient_names']);
        $this->assertArrayNotHasKey('steps', $recipes[0]);
    }

    public function test_someone_elses_recipe_is_not_found(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $id = $this->createRecipe($owner);

        $this->actingAs($stranger, 'sanctum');
        $this->getJson("/api/recipes/{$id}")->assertNotFound();
        $this->postJson("/api/recipes/{$id}/update", $this->payload(['cookbook_id' => Recipe::find($id)->cookbook_id, 'title' => 'Overgenomen']))->assertNotFound();
        $this->postJson("/api/recipes/{$id}/favorite", ['favorite' => true])->assertNotFound();
        $this->deleteJson("/api/recipes/{$id}/delete")->assertNotFound();
        $this->getJson("/api/recipes/{$id}/image")->assertNotFound();
        $this->assertSame('Pasta pesto', Recipe::find($id)->title);
    }

    public function test_an_update_replaces_ingredients_and_steps(): void
    {
        $user = User::factory()->create();
        $id = $this->createRecipe($user);

        $this->actingAs($user, 'sanctum')->postJson("/api/recipes/{$id}/update", $this->payload(['cookbook_id' => Recipe::find($id)->cookbook_id, 
            'title' => 'Pasta pesto met kip',
            'oven_minutes' => 20,
            'ingredients' => [['name' => 'Kipfilet', 'amount' => 300, 'unit' => 'g']],
            'steps' => ['Bak de kip.'],
        ]))->assertOk();

        $recipe = $this->getJson("/api/recipes/{$id}")->json('data');
        $this->assertSame('Pasta pesto met kip', $recipe['title']);
        $this->assertSame(['Kipfilet'], array_column($recipe['ingredients'], 'name'));
        $this->assertSame(['Bak de kip.'], $recipe['steps']);
        $this->assertSame(20, $recipe['oven_minutes']);
        $this->assertSame(200, $recipe['oven_celsius']);
    }

    public function test_invalid_recipes_are_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/recipes/store', $this->payload(['title' => '']))->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/recipes/store', $this->payload(['category' => 'snack']))->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson('/api/recipes/store', $this->payload(['servings' => 0]))->assertUnprocessable()->assertJsonValidationErrors('servings');
        $this->postJson('/api/recipes/store', $this->payload(['source_url' => 'javascript:alert(1)']))->assertUnprocessable()->assertJsonValidationErrors('source_url');
        $this->postJson('/api/recipes/store', $this->payload(['ingredients' => [['amount' => 1]]]))->assertUnprocessable()->assertJsonValidationErrors('ingredients.0.name');
        $this->assertSame(0, Recipe::count());
    }

    public function test_messages_are_in_the_users_language(): void
    {
        $english = User::factory()->create(['language' => 'en']);
        $dutch = User::factory()->create(['language' => 'nl']);

        $this->actingAs($english, 'sanctum')->postJson('/api/recipes/store', $this->payload(['title' => '']))
            ->assertUnprocessable()->assertJsonPath('errors.title.0', 'Please enter a title.');
        $this->actingAs($dutch, 'sanctum')->postJson('/api/recipes/store', $this->payload(['title' => '']))
            ->assertUnprocessable()->assertJsonPath('errors.title.0', 'Vul een titel in.');

        $id = $this->createRecipe($english);
        $this->actingAs($english, 'sanctum')->deleteJson("/api/recipes/{$id}/delete")
            ->assertOk()->assertJsonPath('message', 'Recipe deleted.');
    }

    public function test_members_of_a_shared_cookbook_see_and_edit_its_recipes(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $id = $this->createRecipe($owner);
        $this->share($this->cookbookOf($owner), $partner);

        $this->actingAs($partner, 'sanctum');
        $this->assertSame([$id], array_column($this->getJson('/api/recipes/index')->json('data'), 'id'));
        $this->postJson("/api/recipes/{$id}/update", $this->payload(['cookbook_id' => Recipe::find($id)->cookbook_id, 'title' => 'Aangepast door partner']))->assertOk();
        $this->assertSame('Aangepast door partner', Recipe::find($id)->title);
        // Who added it stays the same
        $this->assertSame($owner->id, Recipe::find($id)->created_by);
    }

    public function test_favorites_are_personal(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $id = $this->createRecipe($owner);
        $this->share($this->cookbookOf($owner), $partner);

        $this->actingAs($partner, 'sanctum')->postJson("/api/recipes/{$id}/favorite", ['favorite' => true])->assertOk();
        // Twice is the same as once
        $this->postJson("/api/recipes/{$id}/favorite", ['favorite' => true])->assertOk();
        $this->assertTrue($this->getJson("/api/recipes/{$id}")->json('data.favorite'));
        $this->assertFalse($this->actingAs($owner, 'sanctum')->getJson("/api/recipes/{$id}")->json('data.favorite'));

        $this->actingAs($partner, 'sanctum')->postJson("/api/recipes/{$id}/favorite", ['favorite' => false])->assertOk();
        $this->assertFalse($this->getJson('/api/recipes/index')->json('data.0.favorite'));
    }

    public function test_a_photo_is_stored_privately_as_a_smaller_jpeg(): void
    {
        $user = User::factory()->create();
        $id = $this->createRecipe($user);

        $url = $this->actingAs($user, 'sanctum')
            ->postJson("/api/recipes/{$id}/image", ['image' => $this->photoDataUrl(2400, 1200)])
            ->assertOk()->json('data.image_url');
        $this->assertStringContainsString("/api/recipes/{$id}/image?v=", $url);
        $thumbUrl = $this->getJson('/api/recipes/index')->json('data.0.thumb_url');
        $this->assertSame($url . '&size=thumb', $thumbUrl);

        $path = Recipe::find($id)->image_path;
        Storage::disk(Recipe::IMAGE_DISK)->assertExists($path);
        $stored = Storage::disk(Recipe::IMAGE_DISK)->get($path);
        $this->assertStringStartsWith("\xFF\xD8", $stored);
        [$width, $height] = getimagesizefromstring($stored);
        $this->assertSame([1600, 800], [$width, $height]);

        $response = $this->get("/api/recipes/{$id}/image")->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        // The small copy for the overview
        $thumb = Storage::disk(Recipe::IMAGE_DISK)->get(Recipe::thumbPath($path));
        $this->assertSame([480, 240], array_slice(getimagesizefromstring($thumb), 0, 2));
        $this->assertSame($thumb, $this->get("/api/recipes/{$id}/image?size=thumb")->assertOk()->streamedContent());
        $this->assertSame($url, $this->getJson("/api/recipes/{$id}")->json('data.image_url'));
    }

    public function test_a_new_photo_replaces_the_old_file(): void
    {
        $user = User::factory()->create();
        $id = $this->createRecipe($user);
        $this->actingAs($user, 'sanctum');

        $this->postJson("/api/recipes/{$id}/image", ['image' => $this->photoDataUrl(800, 600)])->assertOk();
        $first = Recipe::find($id)->image_path;
        $this->postJson("/api/recipes/{$id}/image", ['image' => $this->photoDataUrl()])->assertOk();

        Storage::disk(Recipe::IMAGE_DISK)->assertMissing([$first, Recipe::thumbPath($first)]);
        Storage::disk(Recipe::IMAGE_DISK)->assertExists(Recipe::find($id)->image_path);

        $this->deleteJson("/api/recipes/{$id}/image/delete")->assertOk();
        $this->assertSame([], Storage::disk(Recipe::IMAGE_DISK)->allFiles());
        $this->assertNull(Recipe::find($id)->image_path);
        $this->assertNull($this->getJson("/api/recipes/{$id}")->json('data.image_url'));
        $this->get("/api/recipes/{$id}/image")->assertNotFound();
    }

    public function test_something_that_isnt_a_photo_is_refused(): void
    {
        $user = User::factory()->create();
        $id = $this->createRecipe($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/recipes/{$id}/image", ['image' => 'data:text/html;base64,' . base64_encode('<script>')])
            ->assertUnprocessable();
        $this->postJson("/api/recipes/{$id}/image", [])->assertUnprocessable()->assertJsonValidationErrors('image');
        // Something that says it's a photo but isn't one
        $this->postJson("/api/recipes/{$id}/image", ['image' => 'data:image/jpeg;base64,' . base64_encode('<script>alert(1)</script>')])
            ->assertUnprocessable()->assertJsonPath('errors.image.0', 'Dit is geen geldige foto.');
        $this->assertSame([], Storage::disk(Recipe::IMAGE_DISK)->allFiles());
        $this->assertNull(Recipe::find($id)->image_path);
    }

    public function test_deleting_a_recipe_removes_its_photo(): void
    {
        $user = User::factory()->create();
        $id = $this->createRecipe($user);
        $this->actingAs($user, 'sanctum')->postJson("/api/recipes/{$id}/image", ['image' => $this->photoDataUrl()]);
        $path = Recipe::find($id)->image_path;

        $this->deleteJson("/api/recipes/{$id}/delete")->assertOk();
        $this->assertNull(Recipe::find($id));
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $id]);
        Storage::disk(Recipe::IMAGE_DISK)->assertMissing([$path, Recipe::thumbPath($path)]);
    }

    public function test_deleting_an_account_removes_their_cookbook_with_its_photos(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->createRecipe($user);
        $othersId = $this->createRecipe($other);
        $this->actingAs($user, 'sanctum')->postJson("/api/recipes/{$id}/image", ['image' => $this->photoDataUrl()]);
        $path = Recipe::find($id)->image_path;

        $this->postJson('/api/deactivate')->assertOk();

        $this->assertNull(Recipe::find($id));
        $this->assertSame(0, Cookbook::where('created_by', $user->id)->count());
        Storage::disk(Recipe::IMAGE_DISK)->assertMissing([$path, Recipe::thumbPath($path)]);
        $this->assertNotNull(Recipe::find($othersId));
    }

    public function test_a_recipe_added_to_someone_elses_cookbook_stays_after_deleting_the_account(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $cookbook = $this->cookbookOf($owner);
        $this->share($cookbook, $partner);
        $id = $this->createRecipe($partner, ['cookbook_id' => $cookbook->id]);

        $this->actingAs($partner, 'sanctum')->postJson('/api/deactivate')->assertOk();

        $this->assertSame([$owner->id], $cookbook->memberIds());
        $this->assertNull(Recipe::find($id)->created_by);
        $this->actingAs($owner, 'sanctum')->getJson("/api/recipes/{$id}")->assertOk()->assertJsonPath('data.created_by', null);
    }

    public function test_the_e2e_cleanup_removes_test_recipes_and_photos(): void
    {
        Artisan::call('e2e:user', ['action' => 'create', 'email' => 'recipes@e2e.test']);
        $user = User::where('email', 'recipes@e2e.test')->sole();
        $id = $this->createRecipe($user);
        $this->actingAs($user, 'sanctum')->postJson("/api/recipes/{$id}/image", ['image' => $this->photoDataUrl()]);
        $path = Recipe::find($id)->image_path;

        $this->assertSame(0, Artisan::call('e2e:user', ['action' => 'delete', 'email' => 'recipes@e2e.test']));

        $this->assertSame(0, Recipe::count());
        $this->assertSame(0, Cookbook::count());
        Storage::disk(Recipe::IMAGE_DISK)->assertMissing($path);
    }

    public function test_photo_addresses_use_the_apis_public_address(): void
    {
        // APP_URL is the website; the photos are on the API
        config(['app.url' => 'https://example.com', 'app.api_url' => 'https://api.example.com']);
        $user = User::factory()->create();
        $id = $this->createRecipe($user);

        // The request comes in as plain http (like behind a proxy); the address the app gets is still https
        $url = $this->actingAs($user, 'sanctum')->postJson("http://localhost/api/recipes/{$id}/image", ['image' => $this->photoDataUrl()])
            ->assertOk()->json('data.image_url');

        $this->assertStringStartsWith("https://api.example.com/api/recipes/{$id}/image?v=", $url);
    }
}
