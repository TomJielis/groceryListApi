<?php

namespace App\Http\Requests\Recipe;

// Shows several recipes in one more cookbook; which recipes and which cookbook the user may use is checked by the
// controller
class RecipeShowManyInRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipe_ids' => 'required|array|min:1|max:500',
            'recipe_ids.*' => 'integer',
            'cookbook_id' => 'required|integer',
        ];
    }

    /**
     * @return list<int>
     */
    public function recipeIds(): array
    {
        return array_map('intval', array_values($this->validated('recipe_ids')));
    }

    public function cookbookId(): int
    {
        return (int) $this->validated('cookbook_id');
    }
}
