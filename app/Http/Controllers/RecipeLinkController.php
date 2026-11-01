<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeLink\RecipeLinkSaveRequest;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeLink;
use App\Services\RecipeImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recipe links: a recipe shared with a link (WhatsApp and the like). Whoever opens it reads the recipe, also without
 * an account (the website shows it), and can save their own copy in a cookbook of theirs: a separate recipe with its
 * own photo, which they change without touching the original. Anyone who can see a recipe may share it.
 *
 * Whether someone has the recipe already goes by where it came from, not its name (two "Pannenkoeken" can differ):
 * a copy remembers the recipe it was saved from (copied_from_recipe_id), and the original may be in one of their
 * cookbooks itself. Saving again stays possible on purpose (a second version to try things with).
 */
class RecipeLinkController extends Controller
{
    public function __construct(private RecipeImageStore $images)
    {
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $recipe = Recipe::visibleTo($request->user())->findOrFail($id);
        $link = RecipeLink::forRecipe($recipe, $request->user());

        return response()->json(['data' => ['url' => $link->url(), 'expires_at' => $link->expires_at]]);
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
    public function show(Request $request, string $token): JsonResponse
    {
        $link = RecipeLink::where('token', $token)->with(['recipe.ingredients', 'recipe.steps', 'createdBy:id,name'])->first();
        if (!$link || !$link->recipe) {
            return response()->json(['data' => ['status' => 'not_found']]);
        }
        if (!$link->isUsable()) {
            return response()->json(['data' => ['status' => 'expired']]);
        }

        $recipe = $link->recipe;
        $user = $request->user('sanctum');

        return response()->json(['data' => [
            'status' => 'valid',
            'shared_by' => $link->createdBy?->name,
            // Only known when logged in: whether they have it already, and where
            'already' => $user ? $this->already($recipe, $user) : null,
            'expires_at' => $link->expires_at,
            'recipe' => [
                'title' => $recipe->title,
                'description' => $recipe->description,
                'category' => $recipe->category,
                'servings' => $recipe->servings,
                'prep_minutes' => $recipe->prep_minutes,
                'cook_minutes' => $recipe->cook_minutes,
                'oven_minutes' => $recipe->oven_minutes,
                'oven_celsius' => $recipe->oven_celsius,
                'difficulty' => $recipe->difficulty,
                'kcal' => $recipe->kcal,
                'protein' => $recipe->protein,
                'carbs' => $recipe->carbs,
                'fat' => $recipe->fat,
                'source_url' => $recipe->source_url,
                // Through the link itself: whoever opens it isn't in the recipe's cookbook
                'image_url' => $recipe->image_path ? $this->apiUrl("/api/recipe-links/{$link->token}/image") : null,
                'ingredients' => $recipe->ingredients->map(fn (RecipeIngredient $ingredient) => [
                    'name' => $ingredient->name,
                    'amount' => $ingredient->amount === null ? null : (float) $ingredient->amount,
                    'unit' => $ingredient->unit,
                ])->values(),
                'steps' => $recipe->steps->pluck('body')->values(),
            ],
        ]]);
    }

    // The recipe's photo while the link works, for whoever opens it (no login: the website shows it too)
    public function image(Request $request, string $token): Response
    {
        $link = RecipeLink::where('token', $token)->with('recipe')->first();
        $disk = Storage::disk(Recipe::IMAGE_DISK);
        abort_unless($link && $link->isUsable() && $link->recipe?->image_path && $disk->exists($link->recipe->image_path), 404);

        return $disk->response($link->recipe->image_path, null, [
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
        $cookbook = Cookbook::visibleTo($user)->find($request->validated('cookbook_id'));
        if (!$cookbook) {
            return response()->json(['message' => __('recipes.no_cookbook_access', [], $language)], 403);
        }

        $original = $link->recipe;
        $disk = Storage::disk(Recipe::IMAGE_DISK);
        $imagePath = $original->image_path && $disk->exists($original->image_path)
            ? $this->images->store($disk->get($original->image_path))
            : null;

        $copy = DB::transaction(function () use ($original, $user, $cookbook, $imagePath) {
            $copy = Recipe::create([
                ...$original->only([
                    'title', 'description', 'category', 'servings', 'prep_minutes', 'cook_minutes', 'oven_minutes',
                    'oven_celsius', 'difficulty', 'kcal', 'protein', 'carbs', 'fat', 'source_url',
                ]),
                'cookbook_id' => $cookbook->id,
                'created_by' => $user->id,
                'copied_from_recipe_id' => $original->id,
                'image_path' => $imagePath,
            ]);
            $copy->replaceParts(
                $original->ingredients->map(fn (RecipeIngredient $ingredient) => $ingredient->only(['name', 'amount', 'unit']))->all(),
                $original->steps->pluck('body')->all(),
            );

            return $copy;
        });

        return response()->json([
            'message' => __('recipe-links.saved', [], $language),
            'data' => ['id' => $copy->id, 'cookbook_id' => $cookbook->id],
        ], 201);
    }

    /**
     * The user has the recipe already: it's in a cookbook of theirs (member, nothing to save), or they saved a copy
     * of it before (copy). Null when neither.
     */
    private function already(Recipe $recipe, $user): ?array
    {
        $own = Recipe::visibleTo($user)
            ->where(fn ($query) => $query->whereKey($recipe->id)->orWhere('copied_from_recipe_id', $recipe->id))
            // The original itself first
            ->orderByRaw('id = ? desc', [$recipe->id])
            ->with('cookbook:id,name')
            ->first();

        return $own ? [
            'type' => $own->id === $recipe->id ? 'member' : 'copy',
            'recipe_id' => $own->id,
            'cookbook_name' => $own->cookbook?->name,
        ] : null;
    }

    // On the API's public address when set (API_URL), like RecipeController's photo addresses
    private function apiUrl(string $path): string
    {
        $apiUrl = rtrim((string) config('app.api_url'), '/');

        return $apiUrl !== '' ? $apiUrl . $path : url($path);
    }
}
