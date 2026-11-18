<?php

namespace App\Http\Requests\Cookbook;

use App\Http\Requests\Recipe\RecipeFormRequest;

// A new cookbook, or renaming one (its creator); a cookbook the user isn't in answers 404 in the controller
class CookbookRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
        ];
    }

    protected function ownMessages(): array
    {
        return [
            'name.required' => 'recipes.validation.cookbook_name_required',
        ];
    }

    public function cookbookName(): string
    {
        return $this->validated('name');
    }
}
