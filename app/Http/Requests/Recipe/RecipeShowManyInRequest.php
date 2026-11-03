<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Foundation\Http\FormRequest;

class RecipeShowManyInRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * Which recipes and which cookbook the user may use is checked by the controller.
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
            'recipe_ids' => 'required|array|min:1|max:500',
            'recipe_ids.*' => 'integer',
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
        return __('recipes.validation.rules', [], $this->user()?->language ?: 'nl');
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
