<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use App\Queries\Cookbook\UserCookbooksQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A recipe in the overview: no steps, and only the ingredient names (for searching on an ingredient).
 * Load Recipe::SUMMARY_WITH.
 *
 * @mixin Recipe
 */
class RecipeSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cookbook_id' => $this->cookbook_id,
            'title' => $this->title,
            'categories' => $this->categories ?? [],
            'servings' => $this->servings,
            'prep_minutes' => $this->prep_minutes,
            'cook_minutes' => $this->cook_minutes,
            'oven_minutes' => $this->oven_minutes,
            'oven_celsius' => $this->oven_celsius,
            'difficulty' => $this->difficulty,
            'kcal' => $this->kcal,
            'image_url' => $this->imageUrl(),
            // The small copy, for the tiles in the overview
            'thumb_url' => $this->imageUrl(true),
            'favorite' => $this->favoritedBy->contains('id', $request->user()->id),
            // In a cookbook of the user's: they may change it; otherwise it's only shown to them
            'editable' => UserCookbooksQuery::has($request, (int) $this->cookbook_id),
            // The user's other cookbooks it's shown in
            'shown_in' => $this->shownIn
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->intersect(UserCookbooksQuery::ids($request))
                ->values(),
            'ingredient_names' => $this->ingredients->pluck('name')->values(),
            'created_at' => $this->created_at,
        ];
    }
}
