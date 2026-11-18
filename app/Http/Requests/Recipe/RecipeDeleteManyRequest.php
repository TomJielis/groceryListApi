<?php

namespace App\Http\Requests\Recipe;

// Several recipes at once; which of them the user may delete is checked by the controller
class RecipeDeleteManyRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipe_ids' => 'required|array|min:1|max:500',
            'recipe_ids.*' => 'integer',
        ];
    }

    /**
     * @return list<int>
     */
    public function recipeIds(): array
    {
        return array_map('intval', array_values($this->validated('recipe_ids')));
    }
}
