<?php

namespace App\Http\Requests\Recipe;

class RecipeFavoriteRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'favorite' => 'required|boolean',
        ];
    }

    public function favorite(): bool
    {
        return (bool) $this->validated('favorite');
    }
}
