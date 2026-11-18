<?php

namespace App\Http\Requests\Recipe;

use App\Data\RecipeInput;
use App\Enums\RecipeCategory;
use App\Models\Recipe;
use Illuminate\Validation\Rule;

// Saving and editing a recipe: the app always sends the whole recipe
class RecipeRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The cookbook it goes into (or moves to)
            'cookbook_id'          => 'required|integer',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            // None, one or more kinds of meal (an empty list: none)
            'categories'           => 'present|array|max:' . count(RecipeCategory::cases()),
            'categories.*'         => ['distinct', Rule::enum(RecipeCategory::class)],
            'servings'             => 'required|integer|min:1|max:100',
            'prep_minutes'         => 'integer|min:0|max:1440',
            'cook_minutes'         => 'nullable|integer|min:0|max:1440',
            'oven_minutes'         => 'nullable|integer|min:0|max:1440',
            'oven_celsius'         => 'nullable|integer|min:30|max:300',
            'difficulty'           => ['required', Rule::in(Recipe::DIFFICULTIES)],
            'kcal'                 => 'nullable|integer|min:0|max:10000',
            'protein'              => 'nullable|integer|min:0|max:1000',
            'carbs'                => 'nullable|integer|min:0|max:1000',
            'fat'                  => 'nullable|integer|min:0|max:1000',
            'source_url'           => 'nullable|url:http,https|max:2048',
            'ingredients'          => 'present|array|max:100',
            'ingredients.*.name'   => 'required|string|max:255',
            'ingredients.*.amount' => 'nullable|numeric|min:0|max:100000',
            'ingredients.*.unit'   => 'nullable|string|max:30',
            'steps'                => 'present|array|max:100',
            'steps.*'              => 'required|string|max:5000',
            // The user's other cookbooks it's also shown in, read-only there (left out: unchanged)
            'shown_in'             => 'sometimes|array|max:100',
            'shown_in.*'           => 'integer',
        ];
    }

    protected function ownMessages(): array
    {
        return [
            'cookbook_id.required'        => 'recipes.validation.cookbook_required',
            'title.required'              => 'recipes.validation.title_required',
            'servings.min'                => 'recipes.validation.servings_min',
            'ingredients.*.name.required' => 'recipes.validation.ingredient_name_required',
            'steps.*.required'            => 'recipes.validation.step_required',
        ];
    }

    public function recipe(): RecipeInput
    {
        return new RecipeInput(
            cookbookId: (int) $this->validated('cookbook_id'),
            fields: [
                ...$this->safe()->except(['ingredients', 'steps', 'cookbook_id', 'categories', 'shown_in']),
                'categories' => array_values($this->validated('categories')),
            ],
            ingredients: $this->validated('ingredients'),
            steps: $this->validated('steps'),
            shownIn: $this->has('shown_in') ? array_map('intval', $this->validated('shown_in') ?? []) : null,
        );
    }
}
