<?php

namespace App\Http\Requests\Recipe;

use App\Services\RecipeImageStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecipeImageRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * Access to the recipe is checked by the controller (404 for someone else's recipe, see RecipeRequest).
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * A data URL like the cards send ("data:image/jpeg;base64,...").
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // ~14 MB of text is a photo of about 10 MB
            'image' => 'required|string|max:14000000',
        ];
    }

    /**
     * Custom message for validation
     *
     * @return array
     */
    public function messages()
    {
        // In the user's language, like the push notifications
        $language = $this->user()?->language ?: 'nl';

        return [
            ...__('recipes.validation.rules', [], $language),
            'image.required' => __('recipes.validation.image_required', [], $language),
            'image.max'      => __('recipes.validation.image_too_large', [], $language),
        ];
    }

    /**
     * The field names in the messages, in the user's language
     *
     * @return array
     */
    public function attributes()
    {
        return __('recipes.validation.attributes', [], $this->user()?->language ?: 'nl');
    }

    /**
     * Something that only claims to be a photo is refused, like a missing one
     *
     * @return array
     */
    public function after()
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && app(RecipeImageStore::class)->decodeDataUrl((string) $this->input('image')) === null) {
                    $validator->errors()->add('image', __('recipes.image_invalid', [], $this->user()?->language ?: 'nl'));
                }
            },
        ];
    }
}
