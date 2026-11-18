<?php

namespace App\Http\Requests\RecipeLink;

use App\Http\Requests\Recipe\RecipeFormRequest;

// The photo of a shared recipe, also without being logged in; ?size=thumb asks for the small copy
class RecipeLinkImageRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Any other size (or none) gives the photo itself, as before: nothing is refused
        return [];
    }

    public function wantsThumb(): bool
    {
        return $this->query('size') === 'thumb';
    }
}
