<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recipe\RecipeAddToListRequest;
use App\Http\Requests\Recipe\RecipeDeleteManyRequest;
use App\Http\Requests\Recipe\RecipeFavoriteRequest;
use App\Http\Requests\Recipe\RecipeImageRequest;
use App\Http\Requests\Recipe\RecipeRequest;
use App\Http\Requests\Recipe\RecipeShowManyInRequest;
use App\Http\Requests\RecipeLink\RecipeLinkSaveRequest;
use App\Http\Resources\GroceryListItemResource;
use App\Http\Resources\RecipeImageResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\Cookbook;
use App\Models\GroceryList;
use App\Models\Recipe;
use App\Services\RecipeService;
use App\Queries\Cookbook\UserCookbooksQuery;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
    public function __construct(private RecipeService $recipes)
    {
    }

    // The overview: A to Z (MySQL's collation ignores capitals and accents); the id breaks ties
    public function index(Request $request): AnonymousResourceCollection
    {
        $recipes = Recipe::visibleTo($request->user())
            ->with(Recipe::SUMMARY_WITH)
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return RecipeSummaryResource::collection($recipes);
    }

    public function show(Request $request, int $id): RecipeResource
    {
        return new RecipeResource($this->readableRecipe($request, $id));
    }

    public function store(RecipeRequest $request): JsonResponse
    {
        $input = $request->recipe();
        $cookbook = $this->cookbook($request, $input->cookbookId);
        abort_unless($this->recipes->mayShowIn($input->shownIn, UserCookbooksQuery::ids($request)), 403, $this->noCookbookAccess($request));

        $recipe = $this->recipes->create($input, $cookbook, $request->user());

        return (new RecipeResource($recipe->fresh()))->response()->setStatusCode(201);
    }

    public function update(RecipeRequest $request, int $id): RecipeResource
    {
        $recipe = $this->editableRecipe($request, $id);
        $input = $request->recipe();
        // Moving it to another cookbook: only to one the user may see
        $this->cookbook($request, $input->cookbookId);
        abort_unless($this->recipes->mayShowIn($input->shownIn, UserCookbooksQuery::ids($request), $recipe), 403, $this->noCookbookAccess($request));

        return new RecipeResource($this->recipes->update($recipe, $input, $request->user())->fresh());
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $this->recipes->delete($this->editableRecipe($request, $id));

        return response()->json(['message' => __('recipes.deleted', [], $this->language($request))]);
    }

    // Deletes several recipes at once, like one by one: only the ones the user may change, the others are left
    public function deleteMany(RecipeDeleteManyRequest $request): JsonResponse
    {
        $count = $this->recipes->deleteMany($request->recipeIds(), $request->user());

        return response()->json([
            'message' => trans_choice('recipes.deleted_many', $count, ['count' => $count], $this->language($request)),
            'deleted' => $count,
        ]);
    }

    public function favorite(RecipeFavoriteRequest $request, int $id): JsonResponse
    {
        $this->recipes->setFavorite($this->ownRecipe($request, $id), $request->user(), $request->favorite());

        return response()->json(['data' => ['favorite' => $request->favorite()]]);
    }

    // The products checked in the app go on a shopping list (RecipeService::addToList)
    public function addToList(RecipeAddToListRequest $request, int $id): AnonymousResourceCollection
    {
        $this->readableRecipe($request, $id);
        // GroceryList's global scope only finds lists the user may see (own and accepted shares)
        $list = GroceryList::find($request->listId());
        abort_unless($list, 403, __('recipes.no_list_access', [], $this->language($request)));

        return GroceryListItemResource::collection($this->recipes->addToList($list, $request->items(), $request->user()));
    }

    /**
     * "Save in my cookbook": an own copy of a recipe the user can open but that isn't theirs (planned in a week menu by
     * someone else), like saving a shared recipe link. Answers 409 when they have it already.
     */
    public function copy(RecipeLinkSaveRequest $request, int $id): JsonResponse
    {
        $language = $this->language($request);
        $original = $this->readableRecipe($request, $id);
        $cookbook = $this->cookbook($request, $request->cookbookId());

        try {
            ['copy' => $copy, 'already' => $already] = $this->recipes->copy($original, $cookbook, $request->user());
        } catch (LockTimeoutException) {
            return response()->json(['message' => __('recipe-links.busy', [], $language)], 429);
        }
        if ($already) {
            return response()->json(['message' => __('recipe-links.already_saved', [], $language), 'data' => $already], 409);
        }

        return response()->json([
            'message' => __('recipe-links.saved', [], $language),
            'data' => ['id' => $copy->id, 'cookbook_id' => $cookbook->id],
        ], 201);
    }

    // A new photo, as a data URL like the cards send ("data:image/jpeg;base64,...")
    public function storeImage(RecipeImageRequest $request, int $id): RecipeImageResource
    {
        return new RecipeImageResource($this->recipes->replaceImage($this->editableRecipe($request, $id), $request->imageBytes()));
    }

    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $this->recipes->removeImage($this->editableRecipe($request, $id));

        return response()->json(['message' => __('recipes.image_deleted', [], $this->language($request))]);
    }

    // The photo, or with ?size=thumb its small copy for the overview
    public function image(Request $request, int $id): Response
    {
        $recipe = $this->readableRecipe($request, $id);
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

    // Shows several recipes at once in one more cookbook of the user's (RecipeService::showManyIn)
    public function showManyIn(RecipeShowManyInRequest $request): JsonResponse
    {
        $cookbook = $this->cookbook($request, $request->cookbookId());
        $count = $this->recipes->showManyIn($request->recipeIds(), $cookbook, $request->user());

        return response()->json([
            'message' => trans_choice('recipes.shown_many_in', $count, ['count' => $count, 'cookbook' => $cookbook->name], $this->language($request)),
            'shown' => $count,
        ]);
    }

    // Stops showing it in a cookbook: by the people in its own cookbook, or by the creator of the one it's shown in
    public function hideFrom(Request $request, int $id, int $cookbookId): JsonResponse
    {
        $recipe = $this->ownRecipe($request, $id);
        $createdThatCookbook = Cookbook::where('created_by', $request->user()->id)->whereKey($cookbookId)->exists();
        abort_unless($this->isEditable($request, $recipe) || $createdThatCookbook, 403, __('recipes.read_only', [], $this->language($request)));

        $recipe->shownIn()->detach($cookbookId);

        return response()->json(['message' => __('recipes.hidden_from', [], $this->language($request))]);
    }

    // A cookbook of the user's (own or shared with them); another one answers 403
    private function cookbook(Request $request, int $id): Cookbook
    {
        $cookbook = Cookbook::visibleTo($request->user())->find($id);
        abort_unless($cookbook, 403, $this->noCookbookAccess($request));

        return $cookbook;
    }

    private function noCookbookAccess(Request $request): string
    {
        return __('recipes.no_cookbook_access', [], $this->language($request));
    }

    // One in the user's recipe book: in a cookbook of theirs, or shown in one
    private function ownRecipe(Request $request, int $id): Recipe
    {
        return Recipe::visibleTo($request->user())->findOrFail($id);
    }

    // One the user may open: also a recipe planned in a week menu they're in (read-only, not in their recipe book)
    private function readableRecipe(Request $request, int $id): Recipe
    {
        return Recipe::readableBy($request->user())->findOrFail($id);
    }

    // One the user may change: in a cookbook of theirs. Only shown to them: 403 with the reason
    private function editableRecipe(Request $request, int $id): Recipe
    {
        $recipe = $this->ownRecipe($request, $id);
        abort_unless($this->isEditable($request, $recipe), 403, __('recipes.read_only', [], $this->language($request)));

        return $recipe;
    }

    private function isEditable(Request $request, Recipe $recipe): bool
    {
        return UserCookbooksQuery::has($request, (int) $recipe->cookbook_id);
    }
}
