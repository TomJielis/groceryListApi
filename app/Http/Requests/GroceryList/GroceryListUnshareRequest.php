<?php

namespace App\Http\Requests\GroceryList;

// Removing someone from a list. Not Laravel validation: without both ids the controller answers 400, as the apps expect.
class GroceryListUnshareRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function isComplete(): bool
    {
        return $this->input('groceryListId') !== null && $this->input('userId') !== null;
    }

    public function listId(): int
    {
        return (int) $this->input('groceryListId');
    }

    public function userId(): int
    {
        return (int) $this->input('userId');
    }
}
