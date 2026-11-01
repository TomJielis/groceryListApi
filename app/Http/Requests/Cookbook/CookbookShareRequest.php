<?php

namespace App\Http\Requests\Cookbook;

use Illuminate\Foundation\Http\FormRequest;

class CookbookShareRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * Access to the cookbook is checked by the controller: one the user isn't in answers 404, like one that doesn't
     * exist.
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
            'email' => 'required|email|max:255',
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
            'email.required' => __('recipes.validation.email_invalid', [], $language),
            'email.email'    => __('recipes.validation.email_invalid', [], $language),
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
