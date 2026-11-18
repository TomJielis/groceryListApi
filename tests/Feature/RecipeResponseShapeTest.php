<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\GroceryList;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// The exact fields the app and the website read from the recipe endpoints: a field added, renamed or dropped by
// accident fails here (RecipeSummaryResource, RecipeResource, RecipeImageResource)
class RecipeResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const SUMMARY = [
        'id', 'cookbook_id', 'title', 'categories', 'servings', 'prep_minutes', 'cook_minutes', 'oven_minutes',
        'oven_celsius', 'difficulty', 'kcal', 'image_url', 'thumb_url', 'favorite', 'editable', 'shown_in',
        'ingredient_names', 'created_at',
    ];

    private const DETAIL = [
        ...self::SUMMARY,
        'description', 'protein', 'carbs', 'fat', 'source_url', 'created_by' => ['id', 'name'], 'cookbook_name',
        'in_recipe_book', 'shown_elsewhere', 'ingredients' => ['*' => ['name', 'amount', 'unit']], 'steps', 'updated_at',
    ];

    private User $user;
    private Cookbook $cookbook;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Recipe::IMAGE_DISK);
        $this->user = User::factory()->create();
        $this->cookbook = Cookbook::create(['created_by' => $this->user->id, 'name' => 'Mijn recepten']);
        $this->actingAs($this->user, 'sanctum');
    }

    private function store(): int
    {
        return $this->postJson('/api/recipes/store', [
            'cookbook_id' => $this->cookbook->id, 'title' => 'Pasta', 'categories' => [], 'servings' => 2,
            'difficulty' => 'easy', 'ingredients' => [['name' => 'Penne', 'amount' => 400, 'unit' => 'g']], 'steps' => ['Kook.'],
        ])->assertCreated()->assertExactJsonStructure(['data' => self::DETAIL])->json('data.id');
    }

    public function test_the_overview_lists_summaries(): void
    {
        $this->store();

        $this->getJson('/api/recipes/index')->assertOk()->assertExactJsonStructure(['data' => ['*' => self::SUMMARY]]);
    }

    public function test_a_recipe_is_shown_whole_when_saved_shown_and_updated(): void
    {
        $id = $this->store();

        $this->getJson("/api/recipes/{$id}")->assertOk()->assertExactJsonStructure(['data' => self::DETAIL]);
        $this->postJson("/api/recipes/{$id}/update", [
            'cookbook_id' => $this->cookbook->id, 'title' => 'Pasta 2', 'categories' => [], 'servings' => 2,
            'difficulty' => 'easy', 'ingredients' => [], 'steps' => [],
        ])->assertOk()->assertExactJsonStructure(['data' => self::DETAIL]);
    }

    public function test_a_new_photo_answers_where_to_load_it(): void
    {
        $id = $this->store();

        $this->postJson("/api/recipes/{$id}/image", ['image' => RecipeTest::photoDataUrl()])
            ->assertOk()->assertExactJsonStructure(['data' => ['image_url', 'thumb_url']]);
    }

    public function test_the_other_answers_keep_their_fields(): void
    {
        $id = $this->store();
        $list = GroceryList::create(['name' => 'Lijst', 'created_by' => $this->user->id]);
        $other = Cookbook::create(['created_by' => $this->user->id, 'name' => 'Tweede']);

        $this->postJson("/api/recipes/{$id}/favorite", ['favorite' => true])->assertOk()->assertExactJson(['data' => ['favorite' => true]]);
        $this->postJson('/api/recipes/show-in', ['recipe_ids' => [$id], 'cookbook_id' => $other->id])->assertOk()->assertExactJsonStructure(['message', 'shown']);
        $this->postJson("/api/recipes/{$id}/add-to-list", ['list_id' => $list->id, 'items' => [['name' => 'penne', 'quantity' => 1]]])
            ->assertOk()->assertJsonStructure(['data' => ['*' => ['id', 'name', 'quantity', 'list_id', 'unit_price']]]);
        $this->postJson('/api/recipes/delete-many', ['recipe_ids' => [$id]])->assertOk()->assertExactJsonStructure(['message', 'deleted']);
    }
}
