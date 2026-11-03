<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recipe\RecipeAddToListRequest;
use App\Http\Requests\Recipe\RecipeDeleteManyRequest;
use App\Http\Requests\Recipe\RecipeFavoriteRequest;
use App\Http\Requests\Recipe\RecipeImageRequest;
use App\Http\Requests\Recipe\RecipeRequest;
use App\Http\Requests\Recipe\RecipeShowManyInRequest;
use App\Jobs\SendListActivityPush;
use App\Models\Cookbook;
use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\RecipeImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recipes in the cookbooks the signed-in user is a member of (their own, and ones shared with them), and recipes of
 * other cookbooks shown in those. A recipe lives in one cookbook (cookbook_id): the people in that one change it.
 * Where it's only shown (cookbook_recipe) it's read-only: favorite and add-to-list still work, editing answers 403.
 * Which of their other cookbooks it's shown in, the people in its own cookbook choose when saving it (shown_in).
 * A recipe the user can't see answers 404, like one that doesn't exist.
 */
class RecipeController extends Controller
{
    public function __construct(private RecipeImageStore $images)
    {
    }

    // The overview: no steps, and only the ingredient names (for searching on an ingredient)
    public function index(Request $request): JsonResponse
    {
        $recipes = Recipe::visibleTo($request->user())
            ->with(['ingredients:id,recipe_id,name,position', 'favoritedBy:id', 'shownIn:id', 'categoryLinks:id,recipe_id,category'])
            // A to Z (MySQL's collation ignores capitals and accents); the id breaks ties
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $recipes->map(fn (Recipe $recipe) => $this->presentSummary($request, $recipe))->values(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->present($request, $this->ownRecipe($request, $id))]);
    }

    public function store(RecipeRequest $request): JsonResponse
    {
        $user = $request->user();
        $cookbook = Cookbook::visibleTo($user)->find($request->validated('cookbook_id'));
        if (!$cookbook || !$this->mayShowIn($request)) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $this->language($request))], 403);
        }

        $recipe = DB::transaction(function () use ($request, $user, $cookbook) {
            $recipe = Recipe::create([
                ...$this->fields($request),
                'cookbook_id' => $cookbook->id,
                'created_by' => $user->id,
            ]);
            $recipe->replaceParts($request->validated('ingredients'), $request->validated('steps'));
            $this->syncShownIn($request, $recipe);

            return $recipe;
        });

        return response()->json(['data' => $this->present($request, $recipe->fresh())], 201);
    }

    public function update(RecipeRequest $request, int $id): JsonResponse
    {
        $recipe = $this->editableRecipe($request, $id);
        // Moving it to another cookbook: only to one the user may see
        $cookbookId = $request->validated('cookbook_id');
        if (!Cookbook::visibleTo($request->user())->whereKey($cookbookId)->exists() || !$this->mayShowIn($request, $recipe)) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $this->language($request))], 403);
        }

        DB::transaction(function () use ($request, $recipe, $cookbookId) {
            $recipe->update([...$this->fields($request, $recipe), 'cookbook_id' => $cookbookId]);
            $recipe->replaceParts($request->validated('ingredients'), $request->validated('steps'));
            $this->syncShownIn($request, $recipe);
            // Moved to a cookbook it was shown in: it lives there now
            $recipe->shownIn()->detach($cookbookId);
        });

        return response()->json(['data' => $this->present($request, $recipe->fresh())]);
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $recipe = $this->editableRecipe($request, $id);
        $recipe->deleteImage();
        $recipe->delete();

        return response()->json(['message' => __('recipes.deleted', [], $this->language($request))]);
    }

    // Favorites are personal: a star in a shared cookbook doesn't change it for the others
    // Deletes several recipes at once, like one by one: only the ones the user may change, the others are left
    public function deleteMany(RecipeDeleteManyRequest $request): JsonResponse
    {
        $recipes = Recipe::editableBy($request->user())->whereIn('recipes.id', $request->validated('recipe_ids'))->get();
        DB::transaction(fn () => $recipes->each->delete());
        // The photos only once the recipes are gone, so a failed delete doesn't leave recipes without their photo
        $recipes->each->deleteImage();

        return response()->json([
            'message' => trans_choice('recipes.deleted_many', $recipes->count(), ['count' => $recipes->count()], $this->language($request)),
            'deleted' => $recipes->count(),
        ]);
    }

    public function favorite(RecipeFavoriteRequest $request, int $id): JsonResponse
    {
        $recipe = $this->ownRecipe($request, $id);
        $data = $request->validated();

        if ($data['favorite']) {
            $recipe->favoritedBy()->syncWithoutDetaching([$request->user()->id]);
        } else {
            $recipe->favoritedBy()->detach($request->user()->id);
        }

        return response()->json(['data' => ['favorite' => (bool) $data['favorite']]]);
    }

    /**
     * The products checked in the app go on a shopping list, the way adding them by hand does: a product that's
     * still open on the list gets the number added to it, anything else becomes a new item (with the price it had
     * last time). The others on the list get the grouped "added N products" push for the new ones.
     */
    public function addToList(RecipeAddToListRequest $request, int $id): JsonResponse
    {
        $this->ownRecipe($request, $id);
        // GroceryList's global scope only finds lists the user may see (own and accepted shares)
        $list = GroceryList::find($request->validated('list_id'));
        if (!$list) {
            return response()->json(['message' => __('recipes.no_list_access', [], $this->language($request))], 403);
        }

        $userId = $request->user()->id;
        $items = DB::transaction(function () use ($request, $list, $userId) {
            return collect($request->validated('items'))->map(function (array $item) use ($list, $userId) {
                // The latest row of a name is the current item (items are versioned per name), matched like
                // GroceryListItemController::store
                $latestItem = GroceryListItem::where('list_id', $list->id)
                    ->where('name', $item['name'])
                    ->orderByDesc('id')
                    ->first();

                if ($latestItem && !$latestItem->checked) {
                    $latestItem->increment('quantity', $item['quantity']);

                    return $latestItem->fresh();
                }

                $listItem = GroceryListItem::create([
                    'name' => ucfirst($item['name']),
                    'quantity' => $item['quantity'],
                    'list_id' => $list->id,
                    'unit_price' => $latestItem?->unit_price,
                ]);
                SendListActivityPush::record((int) $list->id, $userId, 'items_added');

                return $listItem;
            });
        });

        return response()->json(['data' => $items->values()]);
    }

    // A new photo, as a data URL like the cards send ("data:image/jpeg;base64,...")
    public function storeImage(RecipeImageRequest $request, int $id): JsonResponse
    {
        $recipe = $this->editableRecipe($request, $id);

        // RecipeImageRequest made sure it's a picture
        $path = $this->images->store($this->images->decodeDataUrl($request->validated('image')));
        $recipe->deleteImage();
        $recipe->update(['image_path' => $path]);

        return response()->json(['data' => ['image_url' => $this->imageUrl($recipe), 'thumb_url' => $this->imageUrl($recipe, true)]]);
    }

    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $recipe = $this->editableRecipe($request, $id);
        $recipe->deleteImage();
        $recipe->update(['image_path' => null]);

        return response()->json(['message' => __('recipes.image_deleted', [], $this->language($request))]);
    }

    // The photo, or with ?size=thumb its small copy for the overview
    public function image(Request $request, int $id): Response
    {
        $recipe = $this->ownRecipe($request, $id);
        $disk = Storage::disk(Recipe::IMAGE_DISK);
        abort_unless($recipe->image_path && $disk->exists($recipe->image_path), 404);

        $path = $recipe->image_path;
        // Photos saved before there were small copies have none: the photo itself is sent then
        if ($request->query('size') === 'thumb' && $disk->exists(Recipe::thumbPath($path))) {
            $path = Recipe::thumbPath($path);
        }

        // The URL changes with every new photo (?v=...), so the app may keep it for a long time
        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    /**
     * Shows several recipes at once in one more cookbook of the user's, like ticking it under "Ook tonen in" in each
     * recipe's form. Only recipes the user may change; the ones already in that cookbook (its own, or shown there
     * before) are left as they are.
     */
    public function showManyIn(RecipeShowManyInRequest $request): JsonResponse
    {
        $user = $request->user();
        $language = $this->language($request);
        $cookbook = Cookbook::visibleTo($user)->find($request->validated('cookbook_id'));
        if (!$cookbook) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $language)], 403);
        }

        $recipes = Recipe::editableBy($user)
            ->whereIn('recipes.id', $request->validated('recipe_ids'))
            ->where('recipes.cookbook_id', '!=', $cookbook->id)
            ->whereDoesntHave('shownIn', fn ($query) => $query->whereKey($cookbook->id))
            ->pluck('recipes.id');

        $cookbook->shownRecipes()->attach($recipes->mapWithKeys(fn ($id) => [$id => ['shown_by' => $user->id]])->all());

        return response()->json([
            'message' => trans_choice('recipes.shown_many_in', $recipes->count(), ['count' => $recipes->count(), 'cookbook' => $cookbook->name], $language),
            'shown' => $recipes->count(),
        ]);
    }

    /**
     * The recipe's own fields as the form sent them. An app version from before categories sends one category: a new
     * recipe gets just that one; an edit keeps its categories when the category is the one that version was shown
     * (Recipe::category), so editing it there doesn't lose a snack or a second kind of meal.
     */
    private function fields(RecipeRequest $request, ?Recipe $recipe = null): array
    {
        $fields = $request->safe()->except(['ingredients', 'steps', 'cookbook_id', 'category', 'categories']);
        if ($request->has('categories')) {
            return [...$fields, 'categories' => array_values($request->validated('categories'))];
        }
        // LEGACY(category): for app builds before 60 (one category). Remove once nobody uses them.
        $category = $request->validated('category');
        if ($recipe && $category === $recipe->category) {
            return $fields;
        }

        return [...$fields, 'categories' => [$category]];
    }

    // Stops showing it in a cookbook: by the people in its own cookbook, or by the creator of the one it's shown in
    public function hideFrom(Request $request, int $id, int $cookbookId): JsonResponse
    {
        $user = $request->user();
        $recipe = $this->ownRecipe($request, $id);
        $editable = Recipe::editableBy($user)->whereKey($recipe->id)->exists();
        $createdThatCookbook = Cookbook::where('created_by', $user->id)->whereKey($cookbookId)->exists();
        abort_unless($editable || $createdThatCookbook, 403, __('recipes.read_only', [], $this->language($request)));

        $recipe->shownIn()->detach($cookbookId);

        return response()->json(['message' => __('recipes.hidden_from', [], $this->language($request))]);
    }

    /**
     * Where the recipe is shown besides its own cookbook, as the form sends it (shown_in): the user's other cookbooks
     * they ticked. Cookbooks of others it's shown in stay unless the list leaves them out (taking it out there is
     * fine, putting it in isn't). Without shown_in (older apps) nothing changes.
     */
    private function syncShownIn(RecipeRequest $request, Recipe $recipe): void
    {
        if (!$request->has('shown_in')) {
            return;
        }

        $wanted = collect($request->validated('shown_in'))->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === (int) $recipe->cookbook_id)->unique();
        $current = $recipe->shownIn()->pluck('cookbooks.id')->map(fn ($id) => (int) $id);

        $recipe->shownIn()->detach($current->diff($wanted)->all());
        $recipe->shownIn()->attach(
            $wanted->diff($current)->mapWithKeys(fn ($id) => [$id => ['shown_by' => $request->user()->id]])->all()
        );
    }

    // Only cookbooks of the user's are added to shown_in (the ones it's in already may stay)
    private function mayShowIn(RecipeRequest $request, ?Recipe $recipe = null): bool
    {
        $current = $recipe ? $recipe->shownIn()->pluck('cookbooks.id')->map(fn ($id) => (int) $id)->all() : [];
        $allowed = [...$this->userCookbookIds($request), ...$current];

        return collect($request->validated('shown_in') ?? [])->every(fn ($id) => in_array((int) $id, $allowed, true));
    }

    private function ownRecipe(Request $request, int $id): Recipe
    {
        return Recipe::visibleTo($request->user())->findOrFail($id);
    }

    // One the user may change: in a cookbook of theirs. Only shown to them: 403 with the reason
    private function editableRecipe(Request $request, int $id): Recipe
    {
        $recipe = $this->ownRecipe($request, $id);
        abort_unless($this->isEditable($request, $recipe), 403, __('recipes.read_only', [], $this->language($request)));

        return $recipe;
    }

    // The cookbooks of the user, looked up once per request (the overview asks for every recipe)
    private function userCookbookIds(Request $request): array
    {
        if (!$request->attributes->has('cookbook_ids')) {
            $request->attributes->set('cookbook_ids', Cookbook::visibleTo($request->user())->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return $request->attributes->get('cookbook_ids');
    }

    private function isEditable(Request $request, Recipe $recipe): bool
    {
        return in_array((int) $recipe->cookbook_id, $this->userCookbookIds($request), true);
    }

    // Where the app loads the photo (or its small copy); the version changes with every new photo, so a cached old
    // one isn't shown. On the API's public address (API_URL) when that's set: behind a proxy the request itself can
    // look like plain http or localhost, and iOS doesn't load photos from such an address. (APP_URL is the website.)
    private function imageUrl(Recipe $recipe, bool $thumb = false): ?string
    {
        if (!$recipe->image_path) {
            return null;
        }

        $path = "/api/recipes/{$recipe->id}/image";
        $apiUrl = rtrim((string) config('app.api_url'), '/');
        $base = $apiUrl !== '' ? $apiUrl . $path : url($path);

        return $base . '?v=' . substr(md5($recipe->image_path), 0, 8) . ($thumb ? '&size=thumb' : '');
    }

    private function presentSummary(Request $request, Recipe $recipe): array
    {
        $userId = $request->user()->id;

        return [
            'id' => $recipe->id,
            'cookbook_id' => $recipe->cookbook_id,
            'title' => $recipe->title,
            'categories' => $recipe->categories ?? [],
            // LEGACY(category): for app builds before 60 (one category). Remove once nobody uses them.
            'category' => $recipe->category,
            'servings' => $recipe->servings,
            'prep_minutes' => $recipe->prep_minutes,
            'cook_minutes' => $recipe->cook_minutes,
            'oven_minutes' => $recipe->oven_minutes,
            'oven_celsius' => $recipe->oven_celsius,
            'difficulty' => $recipe->difficulty,
            'kcal' => $recipe->kcal,
            'image_url' => $this->imageUrl($recipe),
            // The small copy, for the tiles in the overview
            'thumb_url' => $this->imageUrl($recipe, true),
            'favorite' => $recipe->favoritedBy->contains('id', $userId),
            // In a cookbook of the user's: they may change it; otherwise it's only shown to them
            'editable' => $this->isEditable($request, $recipe),
            // The user's other cookbooks it's shown in
            'shown_in' => $recipe->shownIn
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->intersect($this->userCookbookIds($request))
                ->values(),
            'ingredient_names' => $recipe->ingredients->pluck('name')->values(),
            'created_at' => $recipe->created_at,
        ];
    }

    // The whole recipe, as the recipe screen shows it
    private function present(Request $request, Recipe $recipe): array
    {
        $recipe->loadMissing(['ingredients', 'steps', 'createdBy:id,name', 'favoritedBy:id', 'shownIn:id,name', 'cookbook:id,name', 'categoryLinks']);
        $editable = $this->isEditable($request, $recipe);

        return [
            ...$this->presentSummary($request, $recipe),
            'description' => $recipe->description,
            'protein' => $recipe->protein,
            'carbs' => $recipe->carbs,
            'fat' => $recipe->fat,
            'source_url' => $recipe->source_url,
            'created_by' => $recipe->createdBy,
            // The cookbook it lives in (shown with a recipe that's read-only for the user: "from 'Samen koken'")
            'cookbook_name' => $recipe->cookbook?->name,
            // For the people who change it: the cookbooks of others it's shown in (someone in its cookbook put it
            // there), so they see where it is and can take it out again
            'shown_elsewhere' => $editable
                ? $recipe->shownIn
                    ->reject(fn (Cookbook $cookbook) => in_array((int) $cookbook->id, $this->userCookbookIds($request), true))
                    ->map(fn (Cookbook $cookbook) => ['id' => $cookbook->id, 'name' => $cookbook->name])
                    ->values()
                : [],
            'ingredients' => $recipe->ingredients->map(fn (RecipeIngredient $ingredient) => [
                'name' => $ingredient->name,
                'amount' => $ingredient->amount === null ? null : (float) $ingredient->amount,
                'unit' => $ingredient->unit,
            ])->values(),
            'steps' => $recipe->steps->pluck('body')->values(),
            'updated_at' => $recipe->updated_at,
        ];
    }
}
