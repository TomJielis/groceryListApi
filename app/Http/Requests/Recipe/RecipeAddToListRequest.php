<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Foundation\Http\FormRequest;

class RecipeAddToListRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * Access to the recipe and the list is checked by the controller (404 for someone else's recipe, 403 for a list
     * the user isn't on, like adding a product by hand).
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * The products as checked in the app: each with the number that goes on the list.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'list_id'          => 'required|integer',
            'items'            => 'required|array|min:1|max:100',
            'items.*.name'     => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
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
            'list_id.required' => __('recipes.validation.list_required', [], $language),
            'items.required'   => __('recipes.validation.items_required', [], $language),
            'items.min'        => __('recipes.validation.items_required', [], $language),
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
