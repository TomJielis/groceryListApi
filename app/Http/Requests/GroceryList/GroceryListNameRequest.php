<?php

namespace App\Http\Requests\GroceryList;

// A new list (a name is needed) or a renamed one (without a name it keeps its own). A missing name used to fail in the
// database (500); now it's a 422 in the user's language.
class GroceryListNameRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A new list has no list in its route yet
            'name' => [$this->route('groceryList') === null ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('lists.validation.name_required', [], $this->language()),
            'name.string' => __('lists.validation.name_required', [], $this->language()),
            'name.max' => __('lists.validation.name_too_long', [], $this->language()),
        ];
    }

    // Null when not sent (renaming then keeps the name)
    public function listName(): ?string
    {
        return $this->validated('name');
    }
}
