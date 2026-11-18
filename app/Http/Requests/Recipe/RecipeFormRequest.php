<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The base of the recipe requests: messages and field names in the user's language, like the push notifications.
 * Access to the recipe, cookbook or list is checked by the controller: someone else's recipe answers 404 there, like
 * one that doesn't exist (a 403 here would reveal that it does).
 */
abstract class RecipeFormRequest extends FormRequest
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
            ...collect($this->ownMessages())->map(fn (string $key) => __($key, [], $this->language()))->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('recipes.validation.attributes', [], $this->language());
    }

    /**
     * Messages of this request besides the shared ones: rule => translation key
     *
     * @return array<string, string>
     */
    protected function ownMessages(): array
    {
        return [];
    }

    protected function language(): string
    {
        return $this->user()?->language ?: 'nl';
    }
}
