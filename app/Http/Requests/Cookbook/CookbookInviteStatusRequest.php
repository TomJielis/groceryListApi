<?php

namespace App\Http\Requests\Cookbook;

use Illuminate\Foundation\Http\FormRequest;

class CookbookInviteStatusRequest extends FormRequest
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
            'status' => 'required|in:accepted,declined',
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
