<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Cookbooks work like grocery lists: several per user, each private or shared by invite link. Only the creator renames one,
// decides who is in it and deletes it, for everyone.
class CookbookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(Recipe::IMAGE_DISK);
    }

    // Made through the API, like the app does; the user stays the one acting
    private function newCookbook(User $user, string $name = 'Mijn recepten'): int
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/cookbooks/store', ['name' => $name])->assertCreated()->json('data.id');
    }

    // Into the given cookbook, or the user's oldest one
    private function createRecipe(User $user, ?int $cookbookId = null, string $title = 'Pasta'): int
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/recipes/store', array_filter([
            'cookbook_id' => $cookbookId ?? Cookbook::visibleTo($user)->orderBy('id')->value('id'),
            'title' => $title,
            'categories' => ['main'],
            'servings' => 4,
            'difficulty' => 'easy',
            'ingredients' => [],
            'steps' => [],
        ], fn ($value) => $value !== null))->assertCreated()->json('data.id');
    }

    private function isMember(int $cookbookId, User $user): bool
    {
        return in_array($user->id, Cookbook::find($cookbookId)->memberIds(), true);
    }

    // The creator makes an invite link and the partner accepts it, like in the app
    private function shareAndAccept(User $owner, int $cookbookId, User $partner): void
    {
        $token = $this->actingAs($owner, 'sanctum')->postJson("/api/cookbooks/{$cookbookId}/invite-link")->assertOk()->json('data.token');
        $this->actingAs($partner, 'sanctum')->postJson("/api/invite-links/{$token}/accept")->assertOk();
    }

    public function test_registering_gives_a_first_cookbook_in_the_users_language_as_favourite(): void
    {
        foreach (['nl' => 'Mijn kookboek', 'en' => 'My cookbook'] as $language => $name) {
            $this->postJson('/api/register', [
                'name' => 'Sam', 'email' => "sam-{$language}@example.com", 'password' => 'long-enough', 'language' => $language,
                'acceptedTerms' => true, 'acceptedTermsVersion' => '1.3',
            ])->assertSuccessful();
            $user = User::where('email', "sam-{$language}@example.com")->first();

            $this->actingAs($user, 'sanctum')->getJson('/api/cookbooks/index')
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', $name)
                ->assertJsonPath('data.0.created_by.id', $user->id)
                ->assertJsonPath('data.0.favorite', true);
        }
    }

    public function test_own_cookbooks_come_first_then_the_shared_ones(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        // Shared with the partner before they made their own
        $shared = $this->newCookbook($owner, 'Samen koken');
        $this->shareAndAccept($owner, $shared, $partner);
        $own = $this->newCookbook($partner, 'Mijn kookboek');
        $second = $this->newCookbook($partner, 'Taarten');

        $ids = array_column($this->actingAs($partner, 'sanctum')->getJson('/api/cookbooks/index')->json('data'), 'id');
        $this->assertSame([$own, $second, $shared], $ids);
    }

    public function test_users_from_before_get_a_first_cookbook_unless_they_have_one(): void
    {
        $without = User::factory()->create(['language' => 'en']);
        $with = User::factory()->create();
        $this->newCookbook($with, 'Mijn recepten');
        $this->actingAs($with, 'sanctum')->postJson('/api/cookbooks/favorite', ['cookbook_id' => null])->assertOk();

        (require database_path('migrations/2026_11_02_000004_create_first_cookbook_for_existing_users.php'))->up();

        $this->assertSame(['My cookbook'], Cookbook::where('created_by', $without->id)->pluck('name')->all());
        $this->assertNotNull($without->fresh()->favorite_cookbook_id);
        $this->assertSame(['Mijn recepten'], Cookbook::where('created_by', $with->id)->pluck('name')->all());
        $this->assertNull($with->fresh()->favorite_cookbook_id);
    }

    // Someone who deleted their cookbooks (or users made without registering)
    public function test_without_a_cookbook_a_recipe_has_nowhere_to_go(): void
    {
        $user = User::factory()->create(['language' => 'en']);

        $this->actingAs($user, 'sanctum')->getJson('/api/cookbooks/index')->assertOk()->assertJsonPath('data', []);
        $this->assertSame(0, Cookbook::count());
        // A recipe needs one to go into
        $this->postJson('/api/recipes/store', [
            'title' => 'Pasta', 'categories' => ['main'], 'servings' => 4, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
        ])->assertUnprocessable()->assertJsonPath('errors.cookbook_id.0', 'Please choose a cookbook.');
        $this->assertSame(0, Recipe::count());
    }

    public function test_cookbooks_can_be_made_renamed_and_recipes_go_into_the_chosen_one(): void
    {
        $user = User::factory()->create();
        $first = $this->newCookbook($user);
        $shared = $this->postJson('/api/cookbooks/store', ['name' => 'Samen koken'])->assertCreated()->json('data.id');
        $this->postJson("/api/cookbooks/{$shared}/update", ['name' => 'Samen'])->assertOk()->assertJsonPath('data.name', 'Samen');

        $inFirst = $this->createRecipe($user);
        $inShared = $this->createRecipe($user, $shared);

        $this->assertSame($first, Recipe::find($inFirst)->cookbook_id);
        $this->assertSame($shared, Recipe::find($inShared)->cookbook_id);
        $books = collect($this->getJson('/api/cookbooks/index')->json('data'))->keyBy('id');
        $this->assertSame(1, $books[$first]['recipes_count']);
        $this->assertSame(1, $books[$shared]['recipes_count']);
        $this->postJson('/api/cookbooks/store', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_a_recipe_can_be_moved_but_only_to_an_own_cookbook(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $other = $this->actingAs($user, 'sanctum')->postJson('/api/cookbooks/store', ['name' => 'Tweede'])->json('data.id');
        $strangers = $this->newCookbook($stranger);
        $id = $this->createRecipe($user);
        $update = fn (int $cookbookId) => $this->actingAs($user, 'sanctum')->postJson("/api/recipes/{$id}/update", [
            'cookbook_id' => $cookbookId, 'title' => 'Pasta', 'categories' => ['main'], 'servings' => 4, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
        ]);

        $update($other)->assertOk()->assertJsonPath('data.cookbook_id', $other);
        $update($strangers)->assertForbidden();
        $this->assertSame($other, Recipe::find($id)->cookbook_id);
        // Saving straight into someone else's cookbook isn't possible either
        $this->actingAs($user, 'sanctum')->postJson('/api/recipes/store', [
            'cookbook_id' => $strangers, 'title' => 'X', 'categories' => ['main'], 'servings' => 1, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
        ])->assertForbidden();
    }

    public function test_joining_by_invite_link_shows_the_recipes(): void
    {
        $owner = User::factory()->create(['name' => 'Tom']);
        $partner = User::factory()->create();
        $private = $this->newCookbook($owner);
        $shared = $this->postJson('/api/cookbooks/store', ['name' => 'Samen koken'])->json('data.id');
        $this->createRecipe($owner, $private, 'Geheim recept');
        $sharedRecipe = $this->createRecipe($owner, $shared, 'Gedeeld recept');

        // Before joining: nothing of it yet
        $this->actingAs($partner, 'sanctum');
        $this->assertSame([], $this->getJson('/api/recipes/index')->json('data'));

        $this->shareAndAccept($owner, $shared, $partner);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $owner->id && $job->type === 'cookbook_invite_accepted');

        // Only the shared cookbook's recipes. Having a cookbook now, the partner doesn't get an empty one of their own
        // too (like lists, they can make one)
        $this->assertSame([$sharedRecipe], array_column($this->getJson('/api/recipes/index')->json('data'), 'id'));
        $this->assertSame(['Samen koken'], array_column($this->getJson('/api/cookbooks/index')->json('data'), 'name'));
        $members = $this->getJson('/api/cookbooks/index')->json('data.0.members');
        $this->assertSame([$owner->id, $partner->id], array_column($members, 'id'));
        $this->assertSame($owner->id, $this->getJson('/api/cookbooks/index')->json('data.0.created_by.id'));
        // Email invites are gone, and with them the invites waiting to be accepted
        $this->getJson('/api/cookbooks/index')->assertJsonMissingPath('data.0.pending_invites');
    }

    public function test_a_member_leaves_and_the_cookbook_stays_with_the_creator(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->actingAs($owner, 'sanctum')->postJson('/api/cookbooks/store', ['name' => 'Samen'])->json('data.id');
        $recipe = $this->createRecipe($owner, $shared);
        $this->shareAndAccept($owner, $shared, $partner);

        $this->actingAs($partner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/delete")->assertOk();

        $this->assertNotNull(Recipe::find($recipe));
        $this->assertFalse($this->isMember($shared, $partner));
        $this->assertSame([], $this->getJson('/api/recipes/index')->json('data'));
        // Leaving tells nobody
        Queue::assertNotPushed(SendPushNotification::class, fn ($job) => $job->type === 'cookbook_deleted');
    }

    public function test_only_the_creator_renames_and_shares(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->actingAs($owner, 'sanctum')->postJson('/api/cookbooks/store', ['name' => 'Samen'])->json('data.id');
        $this->shareAndAccept($owner, $shared, $partner);

        $this->actingAs($partner, 'sanctum');
        $this->postJson("/api/cookbooks/{$shared}/update", ['name' => 'Van mij'])
            ->assertForbidden()->assertJsonPath('message', 'Alleen de maker van het kookboek kan dit.');
        $this->postJson("/api/cookbooks/{$shared}/invite-link")->assertForbidden();
        $this->assertSame('Samen', Cookbook::find($shared)->name);
        // Adding and editing recipes in it is fine
        $this->createRecipe($partner, $shared, 'Van de partner');
    }

    public function test_the_creator_removes_a_member_but_a_member_cant_remove_others(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->shareAndAccept($owner, $shared, $partner);

        $this->actingAs($partner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/members/{$owner->id}")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/members/{$partner->id}")->assertOk();
        $this->assertFalse($this->isMember($shared, $partner));
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $partner->id && $job->type === 'cookbook_removed');
    }

    public function test_the_creator_deletes_a_cookbook_with_its_recipes_also_their_last_one(): void
    {
        $owner = User::factory()->create();
        $first = $this->newCookbook($owner);
        $second = $this->postJson('/api/cookbooks/store', ['name' => 'Weg ermee'])->json('data.id');
        $recipe = $this->createRecipe($owner, $second);

        $this->deleteJson("/api/cookbooks/{$second}/delete")->assertOk();
        $this->assertNull(Cookbook::find($second));
        $this->assertNull(Recipe::find($recipe));

        // Like lists, the last one can go too
        $this->deleteJson("/api/cookbooks/{$first}/delete")->assertOk();
        $this->assertSame([], $this->getJson('/api/cookbooks/index')->json('data'));
    }

    public function test_deleting_a_shared_cookbook_deletes_it_for_everyone(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->newCookbook($owner, 'Samen');
        $this->shareAndAccept($owner, $shared, $partner);
        $partnersRecipe = $this->createRecipe($partner, $shared, 'Van de partner');
        $this->actingAs($partner, 'sanctum')->postJson("/api/recipes/{$partnersRecipe}/image", ['image' => RecipeTest::photoDataUrl()])->assertOk();
        $this->postJson("/api/recipes/{$partnersRecipe}/favorite", ['favorite' => true])->assertOk();

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/delete")->assertOk();

        $this->assertNull(Cookbook::find($shared));
        $this->assertNull(Recipe::find($partnersRecipe));
        $this->assertSame(0, CookbookInvite::count());
        $this->assertSame([], Storage::disk(Recipe::IMAGE_DISK)->allFiles());
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $partner->id && $job->type === 'cookbook_deleted');
        // The partner has no cookbook left (until they make or accept one)
        $this->assertSame([], $this->actingAs($partner, 'sanctum')->getJson('/api/cookbooks/index')->json('data'));
    }

    public function test_someone_elses_cookbook_is_not_found(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $cookbook = $this->newCookbook($owner);

        $this->actingAs($stranger, 'sanctum');
        $this->postJson("/api/cookbooks/{$cookbook}/update", ['name' => 'Overgenomen'])->assertNotFound();
        $this->postJson("/api/cookbooks/{$cookbook}/invite-link")->assertNotFound();
        $this->deleteJson("/api/cookbooks/{$cookbook}/delete")->assertNotFound();
        $this->assertSame('Mijn recepten', Cookbook::find($cookbook)->name);
    }

    public function test_deleting_the_creators_account_deletes_their_shared_cookbooks(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->shareAndAccept($owner, $shared, $partner);
        $recipe = $this->createRecipe($partner, $shared);
        // A recipe the owner added to the partner's own cookbook stays there
        $partners = $this->actingAs($partner, 'sanctum')->postJson('/api/cookbooks/store', ['name' => 'Van partner'])->json('data.id');
        $this->shareAndAccept($partner, $partners, $owner);
        $ownersRecipe = $this->createRecipe($owner, $partners);

        $this->actingAs($owner, 'sanctum')->postJson('/api/deactivate')->assertOk();

        $this->assertNull(Cookbook::find($shared));
        $this->assertNull(Recipe::find($recipe));
        $this->assertNotNull(Recipe::find($ownersRecipe));
        $this->assertNull(Recipe::find($ownersRecipe)->created_by);
        $this->assertSame([$partner->id], Cookbook::find($partners)->memberIds());
    }

    public function test_a_favorite_cookbook_is_personal_and_forgotten_when_leaving_or_deleted(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $stranger = User::factory()->create();
        $shared = $this->newCookbook($owner, 'Samen');
        $this->shareAndAccept($owner, $shared, $partner);

        $this->actingAs($partner, 'sanctum')->postJson('/api/cookbooks/favorite', ['cookbook_id' => $shared])->assertOk();
        $this->assertTrue($this->getJson('/api/cookbooks/index')->json('data.0.favorite'));
        // Not the owner's favorite
        $this->assertFalse($this->actingAs($owner, 'sanctum')->getJson('/api/cookbooks/index')->json('data.0.favorite'));
        // Only a cookbook you can see
        $this->actingAs($stranger, 'sanctum')->postJson('/api/cookbooks/favorite', ['cookbook_id' => $shared])->assertNotFound();
        $this->assertNull($stranger->fresh()->favorite_cookbook_id);

        // Leaving forgets it
        $this->actingAs($partner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/delete")->assertOk();
        $this->assertNull($partner->fresh()->favorite_cookbook_id);

        // So does deleting it, and clearing it by hand works
        $this->actingAs($owner, 'sanctum')->postJson('/api/cookbooks/favorite', ['cookbook_id' => $shared])->assertOk();
        $this->postJson('/api/cookbooks/favorite', ['cookbook_id' => null])->assertOk();
        $this->assertNull($owner->fresh()->favorite_cookbook_id);
        $this->postJson('/api/cookbooks/favorite', ['cookbook_id' => $shared])->assertOk();
        $this->deleteJson("/api/cookbooks/{$shared}/delete")->assertOk();
        $this->assertNull($owner->fresh()->favorite_cookbook_id);
    }

    public function test_validation_messages_are_in_the_users_language(): void
    {
        $dutch = User::factory()->create(['language' => 'nl']);
        $cookbook = $this->newCookbook($dutch);

        $errors = $this->postJson('/api/recipes/store', [
            'cookbook_id' => $cookbook, 'title' => 'Pasta', 'categories' => ['main'], 'servings' => 150, 'difficulty' => 'easy',
            'ingredients' => [['name' => 'Kaas', 'unit' => str_repeat('x', 31)]], 'steps' => [],
        ])->assertUnprocessable()->json('errors');
        $this->assertSame('Het aantal personen mag hoogstens 100 zijn.', $errors['servings'][0]);
        $this->assertSame('De eenheid van een ingrediënt mag hoogstens 30 tekens hebben.', $errors['ingredients.0.unit'][0]);
        $this->postJson('/api/cookbooks/store', ['name' => str_repeat('x', 101)])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'De naam mag hoogstens 100 tekens hebben.');
    }
}
