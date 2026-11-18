<?php

namespace App\Http\Requests\Recipe;

use App\Services\RecipeImageStore;
use Illuminate\Validation\Validator;

// A new photo as a data URL, like the cards send ("data:image/jpeg;base64,...")
class RecipeImageRequest extends RecipeFormRequest
{
    private ?string $decoded = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ~14 MB of text is a photo of about 10 MB
            'image' => 'required|string|max:14000000',
        ];
    }

    protected function ownMessages(): array
    {
        return [
            'image.required' => 'recipes.validation.image_required',
            'image.max'      => 'recipes.validation.image_too_large',
        ];
    }

    /**
     * Something that only claims to be a photo is refused, like a missing one
     *
     * @return array
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $this->decoded = app(RecipeImageStore::class)->decodeDataUrl((string) $this->input('image'));
                if ($this->decoded === null) {
                    $validator->errors()->add('image', __('recipes.image_invalid', [], $this->language()));
                }
            },
        ];
    }

    // The picture itself (validation made sure it is one)
    public function imageBytes(): string
    {
        return $this->decoded;
    }
}
