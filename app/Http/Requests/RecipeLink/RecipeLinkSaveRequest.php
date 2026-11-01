<?php

namespace App\Http\Requests\RecipeLink;

use Illuminate\Foundation\Http\FormRequest;

class RecipeLinkSaveRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * The cookbook is checked by the controller: one the user can't see answers 403.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // The user's cookbook the copy goes into
            'cookbook_id' => 'required|integer',
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
            'cookbook_id.required' => __('recipes.validation.cookbook_required', [], $language),
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
}
