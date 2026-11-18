<?php

namespace App\Http\Requests\Recipe;

// The products as checked in the app, each with the number that goes on the list. A list the user isn't on answers 403
// in the controller, like adding a product by hand.
class RecipeAddToListRequest extends RecipeFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'list_id'          => 'required|integer',
            'items'            => 'required|array|min:1|max:100',
            'items.*.name'     => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
        ];
    }

    protected function ownMessages(): array
    {
        return [
            'list_id.required' => 'recipes.validation.list_required',
            'items.required'   => 'recipes.validation.items_required',
            'items.min'        => 'recipes.validation.items_required',
        ];
    }

    public function listId(): int
    {
        return (int) $this->validated('list_id');
    }

    /**
     * @return list<array{name: string, quantity: int}>
     */
    public function items(): array
    {
        return array_map(
            fn (array $item) => ['name' => (string) $item['name'], 'quantity' => (int) $item['quantity']],
            array_values($this->validated('items')),
        );
    }
}
