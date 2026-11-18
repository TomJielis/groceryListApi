<?php

namespace App\Http\Resources;

use App\Models\RecipeIngredient;
use App\Models\RecipeLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The recipe a working link shows, also to someone who isn't logged in (load RecipeLink::PRESENTED_WITH). A link that
 * doesn't exist or no longer works answers only its status (RecipeLinkController::show).
 *
 * @mixin RecipeLink
 */
class RecipeLinkViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recipe = $this->recipe;
        $user = $request->user('sanctum');

        return [
            'status' => 'valid',
            'shared_by' => $this->createdBy?->name,
            // Only known when logged in: whether they have it already, and where
            'already' => $user ? $recipe->alreadySavedBy($user) : null,
            'expires_at' => $this->expires_at,
            'recipe' => [
                'title' => $recipe->title,
                'description' => $recipe->description,
                'categories' => $recipe->categories ?? [],
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
                'image_url' => $recipe->image_path ? $this->imageUrl() : null,
                'ingredients' => $recipe->ingredients->map(fn (RecipeIngredient $ingredient) => [
                    'name' => $ingredient->name,
                    'amount' => $ingredient->amount === null ? null : (float) $ingredient->amount,
                    'unit' => $ingredient->unit,
                ])->values(),
                'steps' => $recipe->steps->pluck('body')->values(),
            ],
        ];
    }
}
