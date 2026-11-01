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

// Cookbooks work like grocery lists: several per user, each private or shared by email. Only the creator renames one,
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
            'category' => 'main',
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

    private function shareAndAccept(User $owner, int $cookbookId, User $partner): void
    {
        $this->actingAs($owner, 'sanctum')->postJson("/api/cookbooks/{$cookbookId}/share", ['email' => $partner->email])->assertOk();
        $inviteId = $this->actingAs($partner, 'sanctum')->getJson('/api/cookbooks/pending')->json('data.0.id');
        $this->postJson("/api/cookbooks/pending/{$inviteId}/update-status", ['status' => 'accepted'])->assertOk();
    }

    public function test_nobody_has_a_cookbook_without_making_one(): void
    {
        $user = User::factory()->create(['language' => 'en']);

        $this->actingAs($user, 'sanctum')->getJson('/api/cookbooks/index')->assertOk()->assertJsonPath('data', []);
        $this->assertSame(0, Cookbook::count());
        // A recipe needs one to go into
        $this->postJson('/api/recipes/store', [
            'title' => 'Pasta', 'category' => 'main', 'servings' => 4, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
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
            'cookbook_id' => $cookbookId, 'title' => 'Pasta', 'category' => 'main', 'servings' => 4, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
        ]);

        $update($other)->assertOk()->assertJsonPath('data.cookbook_id', $other);
        $update($strangers)->assertForbidden();
        $this->assertSame($other, Recipe::find($id)->cookbook_id);
        // Saving straight into someone else's cookbook isn't possible either
        $this->actingAs($user, 'sanctum')->postJson('/api/recipes/store', [
            'cookbook_id' => $strangers, 'title' => 'X', 'category' => 'main', 'servings' => 1, 'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
        ])->assertForbidden();
    }

    public function test_sharing_invites_by_email_and_accepting_shows_the_recipes(): void
    {
        $owner = User::factory()->create(['name' => 'Tom']);
        $partner = User::factory()->create();
        $private = $this->newCookbook($owner);
        $shared = $this->postJson('/api/cookbooks/store', ['name' => 'Samen koken'])->json('data.id');
        $this->createRecipe($owner, $private, 'Geheim recept');
        $sharedRecipe = $this->createRecipe($owner, $shared, 'Gedeeld recept');

        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => $partner->email])
            ->assertOk()->assertJsonPath('data.pending_invites.0.email', $partner->email);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $partner->id && $job->type === 'cookbook_shared');

        // Before accepting: nothing of it yet
        $this->actingAs($partner, 'sanctum');
        $this->assertSame([], $this->getJson('/api/recipes/index')->json('data'));
        $pending = $this->getJson('/api/cookbooks/pending')->assertOk()->json('data.0');
        $this->assertSame('Samen koken', $pending['cookbook']['name']);
        $this->assertSame('Tom', $pending['created_by']['name']);

        $this->postJson("/api/cookbooks/pending/{$pending['id']}/update-status", ['status' => 'accepted'])->assertOk();
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $owner->id && $job->type === 'cookbook_invite_accepted');

        // Only the shared cookbook's recipes. Having a cookbook now, the partner doesn't get an empty one of their own
        // too (like lists, they can make one)
        $this->assertSame([$sharedRecipe], array_column($this->getJson('/api/recipes/index')->json('data'), 'id'));
        $this->assertSame(['Samen koken'], array_column($this->getJson('/api/cookbooks/index')->json('data'), 'name'));
        $this->assertSame([], $this->getJson('/api/cookbooks/pending')->json('data'));
        $members = $this->getJson('/api/cookbooks/index')->json('data.0.members');
        $this->assertSame([$owner->id, $partner->id], array_column($members, 'id'));
        $this->assertSame($owner->id, $this->getJson('/api/cookbooks/index')->json('data.0.created_by.id'));
    }

    public function test_an_invite_to_an_address_without_an_account_works_once_they_register(): void
    {
        $owner = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => 'nieuw@example.com'])->assertOk();
        Queue::assertNotPushed(SendPushNotification::class);

        $newcomer = User::factory()->create(['email' => 'nieuw@example.com']);
        $this->shareAccepted($newcomer, $shared);
    }

    private function shareAccepted(User $user, int $cookbookId): void
    {
        $inviteId = $this->actingAs($user, 'sanctum')->getJson('/api/cookbooks/pending')->json('data.0.id');
        $this->postJson("/api/cookbooks/pending/{$inviteId}/update-status", ['status' => 'accepted'])->assertOk();
        $this->assertTrue($this->isMember($cookbookId, $user));
    }

    public function test_declining_adds_nothing_and_someone_elses_invite_cant_be_used(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $stranger = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => $partner->email]);
        $inviteId = CookbookInvite::sole()->id;

        $this->actingAs($stranger, 'sanctum')->postJson("/api/cookbooks/pending/{$inviteId}/update-status", ['status' => 'accepted'])->assertNotFound();
        $this->actingAs($partner, 'sanctum')->postJson("/api/cookbooks/pending/{$inviteId}/update-status", ['status' => 'declined'])->assertOk();

        $this->assertFalse($this->isMember($shared, $partner));
        $this->assertFalse($this->isMember($shared, $stranger));
    }

    public function test_sharing_with_yourself_or_an_existing_member_sends_nothing(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->shareAndAccept($owner, $shared, $partner);
        Queue::fake();

        $this->actingAs($owner, 'sanctum')->postJson("/api/cookbooks/{$shared}/share", ['email' => $owner->email])->assertStatus(400);
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => $partner->email])->assertOk();
        Queue::assertNothingPushed();
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => 'geen-adres'])->assertUnprocessable()->assertJsonValidationErrors('email');
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
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => 'vriend@example.com'])->assertForbidden();
        $this->assertSame('Samen', Cookbook::find($shared)->name);
        $this->assertSame(0, CookbookInvite::where('email', 'vriend@example.com')->count());
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
        $this->postJson("/api/cookbooks/{$cookbook}/share", ['email' => 'a@example.com'])->assertNotFound();
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

    public function test_deleting_an_account_removes_invites_to_its_address(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => $partner->email]);

        $this->actingAs($partner, 'sanctum')->postJson('/api/deactivate')->assertOk();
        $this->assertSame(0, CookbookInvite::count());
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

    public function test_the_creator_withdraws_a_pending_invite_also_to_an_address_without_an_account(): void
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $shared = $this->newCookbook($owner);
        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => 'nieuw@example.com'])->assertOk();
        $inviteId = $this->getJson('/api/cookbooks/index')->json('data.0.pending_invites.0.id');
        $this->shareAndAccept($owner, $shared, $partner);

        // Someone else in it can't
        $this->actingAs($partner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/invites/{$inviteId}")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/cookbooks/{$shared}/invites/{$inviteId}")
            ->assertOk()->assertJsonPath('data.pending_invites', []);
        $this->assertSame(0, CookbookInvite::where('email', 'nieuw@example.com')->count());
        // An accepted invite isn't withdrawn this way (that's removing a member)
        $acceptedId = CookbookInvite::where('user_id', $partner->id)->value('id');
        $this->deleteJson("/api/cookbooks/{$shared}/invites/{$acceptedId}")->assertNotFound();
    }

    public function test_sharing_with_your_own_address_in_other_letters_is_refused(): void
    {
        $owner = User::factory()->create(['email' => 'tom@example.com']);
        $shared = $this->newCookbook($owner);

        $this->postJson("/api/cookbooks/{$shared}/share", ['email' => 'Tom@Example.com'])->assertStatus(400);
        $this->assertSame(0, CookbookInvite::count());
    }

    public function test_validation_messages_are_in_the_users_language(): void
    {
        $dutch = User::factory()->create(['language' => 'nl']);
        $cookbook = $this->newCookbook($dutch);

        $errors = $this->postJson('/api/recipes/store', [
            'cookbook_id' => $cookbook, 'title' => 'Pasta', 'category' => 'main', 'servings' => 150, 'difficulty' => 'easy',
            'ingredients' => [['name' => 'Kaas', 'unit' => str_repeat('x', 31)]], 'steps' => [],
        ])->assertUnprocessable()->json('errors');
        $this->assertSame('Het aantal personen mag hoogstens 100 zijn.', $errors['servings'][0]);
        $this->assertSame('De eenheid van een ingrediënt mag hoogstens 30 tekens hebben.', $errors['ingredients.0.unit'][0]);
        $this->postJson('/api/cookbooks/store', ['name' => str_repeat('x', 101)])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'De naam mag hoogstens 100 tekens hebben.');
    }
}
