<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeLink\RecipeLinkImageRequest;
use App\Http\Requests\RecipeLink\RecipeLinkSaveRequest;
use App\Http\Resources\RecipeLinkResource;
use App\Http\Resources\RecipeLinkViewResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeLink;
use App\Services\RecipeService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recipe links: a recipe shared with a link (WhatsApp and the like). Whoever opens it reads the recipe, also without
 * an account (the website shows it), and can save their own copy in a cookbook of theirs: a separate recipe with its
 * own photo, which they change without touching the original. The people in a recipe's own cookbook may share it
 * (where it's only shown it's not theirs to hand out), and a link stops working once its maker no longer is.
 *
 * Whether someone has the recipe already goes by where it came from, not its name (two "Pannenkoeken" can differ):
 * a copy remembers the recipe it was saved from (copied_from_recipe_id), and the original may be in one of their
 * cookbooks itself. Then saving is refused: no duplicates. Only one they can change counts: a recipe that's only
 * shown to them (read-only) they may still save as their own copy.
 */
class RecipeLinkController extends Controller
{
    public function __construct(private RecipeService $recipes)
    {
    }

    public function store(Request $request, int $id): RecipeLinkResource
    {
        $recipe = Recipe::visibleTo($request->user())->findOrFail($id);
        abort_unless(
            Recipe::editableBy($request->user())->whereKey($recipe->id)->exists(),
            403,
            __('recipe-links.read_only', [], $this->language($request)),
        );

        return new RecipeLinkResource(RecipeLink::forRecipe($recipe, $request->user()));
    }

    // The maker withdraws a link: opening it then shows that it no longer works
    public function delete(Request $request, string $token): JsonResponse
    {
        $link = RecipeLink::where('token', $token)->where('created_by', $request->user()->id)->firstOrFail();
        $link->update(['revoked_at' => now()]);

        return response()->json(['message' => __('recipe-links.withdrawn', [], $this->language($request))]);
    }

    /**
     * The recipe a link shows, also without being logged in: status valid, expired (also withdrawn) or not_found
     */
    public function show(string $token): RecipeLinkViewResource|JsonResponse
    {
        $link = RecipeLink::where('token', $token)->with(RecipeLink::PRESENTED_WITH)->first();
        if (!$link || !$link->recipe) {
            return response()->json(['data' => ['status' => 'not_found']]);
        }
        if (!$link->isUsable()) {
            return response()->json(['data' => ['status' => 'expired']]);
        }

        return new RecipeLinkViewResource($link);
    }

    // The recipe's photo while the link works, for whoever opens it (no login: the website shows it too). ?size=thumb
    // gives the small copy: what WhatsApp and the like show in a link's preview block.
    public function image(RecipeLinkImageRequest $request, string $token): Response
    {
        $link = RecipeLink::where('token', $token)->with('recipe')->first();
        $disk = Storage::disk(Recipe::IMAGE_DISK);
        abort_unless($link && $link->isUsable() && $link->recipe?->image_path && $disk->exists($link->recipe->image_path), 404);

        $path = $link->recipe->image_path;
        if ($request->wantsThumb() && $disk->exists(Recipe::thumbPath($path))) {
            $path = Recipe::thumbPath($path);
        }

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // The user's own copy of the recipe, with its own photo, in one of their cookbooks
    public function save(RecipeLinkSaveRequest $request, string $token): JsonResponse
    {
        $user = $request->user();
        $language = $this->language($request);
        $link = RecipeLink::where('token', $token)->with(['recipe.ingredients', 'recipe.steps'])->first();
        if (!$link || !$link->recipe) {
            return response()->json(['message' => __('recipe-links.not_found', [], $language)], 404);
        }
        if (!$link->isUsable()) {
            return response()->json(['message' => __('recipe-links.expired', [], $language)], 410);
        }
        $cookbook = Cookbook::visibleTo($user)->find($request->cookbookId());
        if (!$cookbook) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $language)], 403);
        }

        // They have it already (a copy, or the recipe itself in a cookbook of theirs): no second copy. One at a time per
        // user and recipe, so a double tap doesn't make two copies either (the second finds the first)
        try {
            ['copy' => $copy, 'already' => $already] = $this->recipes->copy($link->recipe, $cookbook, $user);
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
}
