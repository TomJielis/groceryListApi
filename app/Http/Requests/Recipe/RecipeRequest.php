<?php

namespace App\Http\Requests\Recipe;

use App\Enums\RecipeCategory;
use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecipeRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * Access to the recipe itself is checked by the controller: someone else's recipe answers 404 there, like one
     * that doesn't exist (a 403 here would reveal that it does).
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * The same for saving and editing: the app always sends the whole recipe.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // The cookbook it goes into (or moves to)
            'cookbook_id'          => 'required|integer',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            // None, one or more kinds of meal (an empty list: none)
            'categories'           => 'present|array|max:' . count(RecipeCategory::cases()),
            'categories.*'         => ['distinct', Rule::enum(RecipeCategory::class)],
            'servings'             => 'required|integer|min:1|max:100',
            'prep_minutes'         => 'integer|min:0|max:1440',
            'cook_minutes'         => 'nullable|integer|min:0|max:1440',
            'oven_minutes'         => 'nullable|integer|min:0|max:1440',
            'oven_celsius'         => 'nullable|integer|min:30|max:300',
            'difficulty'           => ['required', Rule::in(Recipe::DIFFICULTIES)],
            'kcal'                 => 'nullable|integer|min:0|max:10000',
            'protein'              => 'nullable|integer|min:0|max:1000',
            'carbs'                => 'nullable|integer|min:0|max:1000',
            'fat'                  => 'nullable|integer|min:0|max:1000',
            'source_url'           => 'nullable|url:http,https|max:2048',
            'ingredients'          => 'present|array|max:100',
            'ingredients.*.name'   => 'required|string|max:255',
            'ingredients.*.amount' => 'nullable|numeric|min:0|max:100000',
            'ingredients.*.unit'   => 'nullable|string|max:30',
            'steps'                => 'present|array|max:100',
            'steps.*'              => 'required|string|max:5000',
            // The user's other cookbooks it's also shown in, read-only there (left out: unchanged)
            'shown_in'             => 'sometimes|array|max:100',
            'shown_in.*'           => 'integer',
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
            'cookbook_id.required'        => __('recipes.validation.cookbook_required', [], $language),
            'title.required'              => __('recipes.validation.title_required', [], $language),
            'servings.min'                => __('recipes.validation.servings_min', [], $language),
            'ingredients.*.name.required' => __('recipes.validation.ingredient_name_required', [], $language),
            'steps.*.required'            => __('recipes.validation.step_required', [], $language),
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
