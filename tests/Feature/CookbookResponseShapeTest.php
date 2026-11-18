<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryListInvitesStatus;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// The exact fields the app and the website read from the cookbook and recipe link endpoints: a field added, renamed or
// dropped by accident fails here (CookbookResource, RecipeLinkResource, RecipeLinkViewResource)
class CookbookResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const COOKBOOK = [
        'id', 'name', 'favorite', 'created_by' => ['id', 'name'], 'recipes_count', 'own_recipes_count',
        'shown_elsewhere' => ['recipes', 'cookbooks'], 'members' => ['*' => ['id', 'name']],
    ];

    // As someone it's shared with sees it: what only the creator needs is null
    private const SHARED_COOKBOOK = [
        'id', 'name', 'favorite', 'created_by' => ['id', 'name'], 'recipes_count', 'own_recipes_count',
        'shown_elsewhere', 'members' => ['*' => ['id', 'name']],
    ];

    private const LINK_VIEW = [
        'status', 'shared_by', 'already' => ['type', 'recipe_id', 'cookbook_name'], 'expires_at',
        'recipe' => [
            'title', 'description', 'categories', 'servings', 'prep_minutes', 'cook_minutes', 'oven_minutes',
            'oven_celsius', 'difficulty', 'kcal', 'protein', 'carbs', 'fat', 'source_url', 'image_url',
            'ingredients' => ['*' => ['name', 'amount', 'unit']], 'steps',
        ],
    ];

    private User $owner;
    private User $friend;
    private Cookbook $cookbook;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(Recipe::IMAGE_DISK);
        $this->owner = User::factory()->create();
        $this->friend = User::factory()->create();
        $this->cookbook = Cookbook::create(['created_by' => $this->owner->id, 'name' => 'Samen']);
        CookbookInvite::create(['cookbook_id' => $this->cookbook->id, 'user_id' => $this->friend->id, 'status' => GroceryListInvitesStatus::ACCEPTED]);
        $this->actingAs($this->owner, 'sanctum');
    }

    public function test_the_overview_lists_cookbooks(): void
    {
        $this->getJson('/api/cookbooks/index')->assertOk()->assertExactJsonStructure(['data' => ['*' => self::COOKBOOK]]);
        $this->actingAs($this->friend, 'sanctum')->getJson('/api/cookbooks/index')->assertOk()
            ->assertJsonPath('data.0.shown_elsewhere', null)
            ->assertExactJsonStructure(['data' => ['*' => self::SHARED_COOKBOOK]]);
    }

    public function test_a_cookbook_is_shown_when_made_renamed_and_someone_removed(): void
    {
        $id = $this->postJson('/api/cookbooks/store', ['name' => 'Nieuw'])
            ->assertCreated()->assertExactJsonStructure(['data' => self::COOKBOOK])->json('data.id');
        $this->postJson("/api/cookbooks/{$id}/update", ['name' => 'Anders'])
            ->assertOk()->assertExactJsonStructure(['data' => self::COOKBOOK]);
        $this->deleteJson("/api/cookbooks/{$this->cookbook->id}/members/{$this->friend->id}")
            ->assertOk()->assertExactJsonStructure(['message', 'data' => self::COOKBOOK]);
    }

    public function test_the_other_cookbook_answers_keep_their_fields(): void
    {
        $this->postJson('/api/cookbooks/favorite', ['cookbook_id' => $this->cookbook->id])->assertOk()->assertExactJsonStructure(['message']);
        $this->actingAs($this->friend, 'sanctum')->deleteJson("/api/cookbooks/{$this->cookbook->id}/delete")
            ->assertOk()->assertExactJsonStructure(['message']);
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/cookbooks/{$this->cookbook->id}/delete")
            ->assertOk()->assertExactJsonStructure(['message']);
    }

    public function test_recipe_link_answers_keep_their_fields(): void
    {
        $recipe = Recipe::create(['cookbook_id' => $this->cookbook->id, 'created_by' => $this->owner->id, 'title' => 'Pasta', 'servings' => 2, 'difficulty' => 'easy']);
        $recipe->replaceParts([['name' => 'Penne', 'amount' => 400, 'unit' => 'g']], ['Kook.']);
        $own = Cookbook::create(['created_by' => $this->friend->id, 'name' => 'Van mij']);

        $url = $this->postJson("/api/recipes/{$recipe->id}/share-link")
            ->assertOk()->assertExactJsonStructure(['data' => ['url', 'expires_at']])->json('data.url');
        $token = basename($url);

        $this->getJson("/api/recipe-links/{$token}")->assertOk()->assertExactJsonStructure(['data' => self::LINK_VIEW]);
        $this->getJson('/api/recipe-links/nope')->assertOk()->assertExactJson(['data' => ['status' => 'not_found']]);

        // Someone who left the cookbook still opens the link, and saves their own copy
        $this->actingAs($this->friend, 'sanctum')->deleteJson("/api/cookbooks/{$this->cookbook->id}/delete")->assertOk();
        $this->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])
            ->assertCreated()->assertExactJsonStructure(['message', 'data' => ['id', 'cookbook_id']]);
        $this->postJson("/api/recipe-links/{$token}/save", ['cookbook_id' => $own->id])
            ->assertConflict()->assertExactJsonStructure(['message', 'data' => ['type', 'recipe_id', 'cookbook_name']]);

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/recipe-links/{$token}/delete")->assertOk()->assertExactJsonStructure(['message']);
        $this->getJson("/api/recipe-links/{$token}")->assertOk()->assertExactJson(['data' => ['status' => 'expired']]);
    }
}
