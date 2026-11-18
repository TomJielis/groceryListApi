<?php

namespace App\Http\Requests\WeekMenu;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The base of the week menu requests: messages and field names in the user's language. Whether the user is in the menu
 * (404 otherwise) or may change it (403) is checked by the controller, like the cookbooks.
 */
abstract class WeekMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return [
            ...__('recipes.validation.rules', [], $this->language()),
            ...__('week-menus.validation.rules', [], $this->language()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('week-menus.validation.attributes', [], $this->language());
    }

    protected function language(): string
    {
        return $this->user()?->language ?: 'nl';
    }
}
