<?php

namespace App\Http\Resources;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Queries\Cookbook\UserCookbooksQuery;
use Illuminate\Http\Request;

/**
 * The whole recipe, as the recipe screen shows it: the overview's fields and the rest.
 * Answers 200 also for a recipe just made (the controller says 201 where it is one).
 *
 * @mixin Recipe
 */
class RecipeResource extends RecipeSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(Recipe::PRESENTED_WITH);
        $editable = UserCookbooksQuery::has($request, (int) $this->cookbook_id);
        $inRecipeBook = Recipe::visibleTo($request->user())->whereKey($this->id)->exists();

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'protein' => $this->protein,
            'carbs' => $this->carbs,
            'fat' => $this->fat,
            'source_url' => $this->source_url,
            'created_by' => $this->createdBy,
            // Not for a recipe only seen through a week menu: which (private) cookbook it lives in stays its owners'
            'cookbook_id' => $inRecipeBook ? $this->cookbook_id : null,
            // The cookbook it lives in (shown with a recipe that's read-only for the user: "from 'Samen koken'")
            // Not for a recipe only seen through a week menu: the name of someone else's private cookbook stays theirs
            'cookbook_name' => $inRecipeBook ? $this->cookbook?->name : null,
            // False for a recipe the user only sees through a week menu: the app then offers saving a copy
            'in_recipe_book' => $inRecipeBook,
            // For the people who change it: the cookbooks of others it's shown in (someone in its cookbook put it
            // there), so they see where it is and can take it out again
            'shown_elsewhere' => $editable
                ? $this->shownIn
                    ->reject(fn (Cookbook $cookbook) => UserCookbooksQuery::has($request, (int) $cookbook->id))
                    ->map(fn (Cookbook $cookbook) => ['id' => $cookbook->id, 'name' => $cookbook->name])
                    ->values()
                : [],
            'ingredients' => $this->ingredients->map(fn (RecipeIngredient $ingredient) => [
                'name' => $ingredient->name,
                'amount' => $ingredient->amount === null ? null : (float) $ingredient->amount,
                'unit' => $ingredient->unit,
            ])->values(),
            'steps' => $this->steps->pluck('body')->values(),
            'updated_at' => $this->updated_at,
        ];
    }
}
