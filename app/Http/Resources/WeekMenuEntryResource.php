<?php

namespace App\Http\Resources;

use App\Models\RecipeIngredient;
use App\Models\WeekMenuEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A planned recipe, with what the app needs to show it, open it and put its ingredients on a list (load
 * WeekMenuEntry::PRESENTED_WITH)
 *
 * @mixin WeekMenuEntry
 */
class WeekMenuEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recipe = $this->recipe;

        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'servings' => $this->servings,
            'position' => $this->position,
            'added_by' => $this->addedBy?->only(['id', 'name']),
            'added_to_list_at' => $this->added_to_list_at,
            'added_to_list_by' => $this->addedToListBy?->only(['id', 'name']),
            // Without a name once that list is deleted
            'added_to_list' => $this->addedToList ? ['id' => $this->addedToList->id, 'name' => $this->addedToList->name] : null,
            'added_to_list_servings' => $this->added_to_list_servings,
            'recipe' => [
                'id' => $recipe->id,
                'title' => $recipe->title,
                'servings' => $recipe->servings,
                'image_url' => $recipe->imageUrl(),
                'thumb_url' => $recipe->imageUrl(true),
                'prep_minutes' => $recipe->prep_minutes,
                'cook_minutes' => $recipe->cook_minutes,
                // For putting the week on a list: the app scales and combines them (utils/week-menu)
                'ingredients' => $recipe->ingredients->map(fn (RecipeIngredient $ingredient) => [
                    'name' => $ingredient->name,
                    'amount' => $ingredient->amount === null ? null : (float) $ingredient->amount,
                    'unit' => $ingredient->unit,
                ])->values(),
            ],
        ];
    }
}
