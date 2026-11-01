<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recipe\RecipeAddToListRequest;
use App\Http\Requests\Recipe\RecipeFavoriteRequest;
use App\Http\Requests\Recipe\RecipeImageRequest;
use App\Http\Requests\Recipe\RecipeRequest;
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
 * Recipes in the cookbooks the signed-in user is a member of (their own, and ones shared with them).
 * A recipe of someone else answers 404, like one that doesn't exist.
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
            ->with(['ingredients:id,recipe_id,name,position', 'favoritedBy:id'])
            // Newest first; the id breaks ties between recipes saved in the same second
            ->orderByDesc('id')
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
        if (!$cookbook) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $this->language($request))], 403);
        }

        $recipe = DB::transaction(function () use ($request, $user, $cookbook) {
            $recipe = Recipe::create([
                ...$request->safe()->except(['ingredients', 'steps', 'cookbook_id']),
                'cookbook_id' => $cookbook->id,
                'created_by' => $user->id,
            ]);
            $recipe->replaceParts($request->validated('ingredients'), $request->validated('steps'));

            return $recipe;
        });

        return response()->json(['data' => $this->present($request, $recipe->fresh())], 201);
    }

    public function update(RecipeRequest $request, int $id): JsonResponse
    {
        $recipe = $this->ownRecipe($request, $id);
        // Moving it to another cookbook: only to one the user may see
        $cookbookId = $request->validated('cookbook_id');
        if (!Cookbook::visibleTo($request->user())->whereKey($cookbookId)->exists()) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $this->language($request))], 403);
        }

        DB::transaction(function () use ($request, $recipe, $cookbookId) {
            $recipe->update([...$request->safe()->except(['ingredients', 'steps', 'cookbook_id']), 'cookbook_id' => $cookbookId]);
            $recipe->replaceParts($request->validated('ingredients'), $request->validated('steps'));
        });

        return response()->json(['data' => $this->present($request, $recipe->fresh())]);
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $recipe = $this->ownRecipe($request, $id);
        $recipe->deleteImage();
        $recipe->delete();

        return response()->json(['message' => __('recipes.deleted', [], $this->language($request))]);
    }

    // Favorites are personal: a star in a shared cookbook doesn't change it for the others
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
        $recipe = $this->ownRecipe($request, $id);

        // RecipeImageRequest made sure it's a picture
        $path = $this->images->store($this->images->decodeDataUrl($request->validated('image')));
        $recipe->deleteImage();
        $recipe->update(['image_path' => $path]);

        return response()->json(['data' => ['image_url' => $this->imageUrl($recipe), 'thumb_url' => $this->imageUrl($recipe, true)]]);
    }

    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $recipe = $this->ownRecipe($request, $id);
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

    private function ownRecipe(Request $request, int $id): Recipe
    {
        return Recipe::visibleTo($request->user())->findOrFail($id);
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
            'ingredient_names' => $recipe->ingredients->pluck('name')->values(),
            'created_at' => $recipe->created_at,
        ];
    }

    // The whole recipe, as the recipe screen shows it
    private function present(Request $request, Recipe $recipe): array
    {
        $recipe->loadMissing(['ingredients', 'steps', 'createdBy:id,name', 'favoritedBy:id']);

        return [
            ...$this->presentSummary($request, $recipe),
            'description' => $recipe->description,
            'protein' => $recipe->protein,
            'carbs' => $recipe->carbs,
            'fat' => $recipe->fat,
            'source_url' => $recipe->source_url,
            'created_by' => $recipe->createdBy,
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
