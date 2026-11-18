<?php

namespace App\Http\Requests\RecipeLink;

use App\Http\Requests\Recipe\RecipeFormRequest;

// Saving a copy of a recipe (a shared link, or one planned in a week menu); a cookbook the user can't see answers 403 in
// the controller
class RecipeLinkSaveRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The user's cookbook the copy goes into
            'cookbook_id' => 'required|integer',
        ];
    }

    protected function ownMessages(): array
    {
        return [
            'cookbook_id.required' => 'recipes.validation.cookbook_required',
        ];
    }

    public function cookbookId(): int
    {
        return (int) $this->validated('cookbook_id');
    }
}
