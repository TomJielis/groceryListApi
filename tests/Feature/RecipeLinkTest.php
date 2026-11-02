<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// A recipe shared with a link: read by anyone with the link, saved as their own copy
class RecipeLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $friend;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Recipe::IMAGE_DISK);
        config(['app.url' => 'https://example.com', 'app.api_url' => 'https://api.example.com']);
        $this->owner = User::factory()->create(['name' => 'Tom']);
        $this->friend = User::factory()->create();

        $cookbook = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $this->owner->id]);
        $this->recipe = Recipe::create([
            'cookbook_id' => $cookbook->id, 'created_by' => $this->owner->id, 'title' => 'Pasta pesto', 'category' => 'main',
            'servings' => 4, 'prep_minutes' => 10, 'difficulty' => 'easy', 'kcal' => 640,
        ]);
        $this->recipe->replaceParts([['name' => 'Penne', 'amount' => 400, 'unit' => 'g']], ['Kook de pasta.']);
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/recipes/{$this->recipe->id}/image", ['image' => RecipeTest::photoDataUrl()])->assertOk();
    }

    private function shareLink(): string
    {
        $url = $this->actingAs($this->owner, 'sanctum')->postJson("/api/recipes/{$this->recipe->id}/share-link")->assertOk()->json('data.url');
        $this->assertStringStartsWith('https://example.com/auth/recipe/', $url);

        return basename($url);
    }

    public function test_anyone_with_the_link_reads_the_recipe_and_its_photo(): void
    {
        $token = $this->shareLink();
        $this->app['auth']->forgetGuards();

        $recipe = $this->getJson("/api/recipe-links/{$token}")->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.shared_by', 'Tom')
            ->json('data.recipe');
        $this->assertSame('Pasta pesto', $recipe['title']);
        $this->assertSame([['name' => 'Penne', 'amount' => 400, 'unit' => 'g']], $recipe['ingredients']);
        $this->assertSame(['Kook de pasta.'], $recipe['steps']);
        $this->assertSame("https://api.example.com/api/recipe-links/{$token}/image", $recipe['image_url']);
        $this->get("/api/recipe-links/{$token}/image")->assertOk();
        // The small copy, for link previews
        $thumb = Storage::disk(Recipe::IMAGE_DISK)->get(Recipe::thumbPath($this->recipe->fresh()->image_path));
        $this->assertSame($thumb, $this->get("/api/recipe-links/{$token}/image?size=thumb")->assertOk()->streamedContent());
    }

    public function test_saving_makes_an_own_copy_with_its_own_photo(): void
    {
        $token = $this->shareLink();
        $own = Cookbook::create(['name' => 'Van de vriend', 'created_by' => $this->friend->id]);

        $copyId = $this->actingAs($this->friend, 'sanctum')->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])
            ->assertCreated()->assertJsonPath('data.cookbook_id', $own->id)->json('data.id');

        $copy = Recipe::find($copyId);
        $this->assertSame('Pasta pesto', $copy->title);
        $this->assertSame($this->friend->id, $copy->created_by);
        $this->assertSame(['Penne'], $copy->ingredients->pluck('name')->all());
        $this->assertSame(['Kook de pasta.'], $copy->steps->pluck('body')->all());
        $this->assertNotNull($copy->image_path);
        $this->assertNotSame($this->recipe->fresh()->image_path, $copy->image_path);
        Storage::disk(Recipe::IMAGE_DISK)->assertExists([$copy->image_path, Recipe::thumbPath($copy->image_path)]);

        // Changing the copy leaves the original alone, and deleting the original leaves the copy
        $copy->update(['title' => 'Mijn pasta']);
        $this->assertSame('Pasta pesto', $this->recipe->fresh()->title);
        $this->recipe->deleteImage();
        $this->recipe->delete();
        $this->assertNotNull(Recipe::find($copyId));
        Storage::disk(Recipe::IMAGE_DISK)->assertExists($copy->image_path);
    }

    public function test_only_into_an_own_cookbook_and_only_a_visible_recipe_can_be_shared(): void
    {
        $token = $this->shareLink();
        $ownersCookbook = $this->recipe->cookbook_id;

        $this->actingAs($this->friend, 'sanctum')->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $ownersCookbook])->assertForbidden();
        $this->postJson("/api/recipe-links/{$token}/save", [])->assertUnprocessable()->assertJsonValidationErrors('cookbook_id');
        $this->postJson("/api/recipes/{$this->recipe->id}/share-link")->assertNotFound();
        $this->assertSame(1, Recipe::count());
    }

    public function test_an_expired_or_withdrawn_link_says_so(): void
    {
        $token = $this->shareLink();
        $own = Cookbook::create(['name' => 'Van de vriend', 'created_by' => $this->friend->id]);

        $this->travel(RecipeLink::DAYS_VALID + 1)->days();
        $this->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.status', 'expired');
        $this->get("/api/recipe-links/{$token}/image")->assertNotFound();
        $this->actingAs($this->friend, 'sanctum')->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])
            ->assertStatus(410)->assertJsonPath('message', 'Deze receptlink is verlopen of ingetrokken. Vraag om een nieuwe link.');
        $this->travelBack();

        RecipeLink::where('token', $token)->update(['expires_at' => now()->subMinute()]);
        $fresh = $this->shareLink();
        $this->actingAs($this->friend, 'sanctum')->deleteJson("/api/recipe-links/{$fresh}/delete")->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/recipe-links/{$fresh}/delete")->assertOk();
        $this->getJson("/api/recipe-links/{$fresh}")->assertJsonPath('data.status', 'expired');
        $this->getJson('/api/recipe-links/does-not-exist')->assertJsonPath('data.status', 'not_found');
    }

    public function test_opening_a_link_says_whether_you_have_the_recipe_already(): void
    {
        $token = $this->shareLink();
        $own = Cookbook::create(['name' => 'Van de vriend', 'created_by' => $this->friend->id]);

        // Not logged in: unknown; logged in without it: no
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.already', null);
        $this->actingAs($this->friend, 'sanctum')->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.already', null);

        // After saving: their copy, also when they renamed it
        $copyId = $this->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])->json('data.id');
        Recipe::find($copyId)->update(['title' => 'Mijn eigen pasta']);
        $this->getJson("/api/recipe-links/{$token}")
            ->assertJsonPath('data.already.type', 'copy')
            ->assertJsonPath('data.already.recipe_id', $copyId)
            ->assertJsonPath('data.already.cookbook_name', 'Van de vriend');

        // Saving it again is refused: no duplicates
        $this->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Dit recept staat al in je kookboek.')
            ->assertJsonPath('data.recipe_id', $copyId);
        $this->assertSame(1, Recipe::where('copied_from_recipe_id', $this->recipe->id)->count());

        // A deleted copy no longer counts
        Recipe::where('copied_from_recipe_id', $this->recipe->id)->get()->each->delete();
        $this->getJson("/api/recipe-links/{$token}")->assertJsonPath('data.already', null);
    }

    public function test_a_recipe_in_a_cookbook_you_are_in_is_yours_already(): void
    {
        $token = $this->shareLink();
        // The owner, whose cookbook it is in: nothing to save
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/recipe-links/{$token}")
            ->assertJsonPath('data.already.type', 'member')
            ->assertJsonPath('data.already.recipe_id', $this->recipe->id)
            ->assertJsonPath('data.already.cookbook_name', 'Mijn recepten');
        $this->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $this->recipe->cookbook_id])->assertStatus(409);
        $this->assertSame(1, Recipe::count());
    }
}
