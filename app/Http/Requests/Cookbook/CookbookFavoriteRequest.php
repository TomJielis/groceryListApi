<?php

namespace App\Http\Requests\Cookbook;

use App\Http\Requests\Recipe\RecipeFormRequest;

// The cookbook new recipes go into from "all recipes"; one the user can't see answers 404 in the controller
class CookbookFavoriteRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Null clears it
            'cookbook_id' => 'present|nullable|integer',
        ];
    }

    public function cookbookId(): ?int
    {
        $id = $this->validated('cookbook_id');

        return $id === null ? null : (int) $id;
    }
}
