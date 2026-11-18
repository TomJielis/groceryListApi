<?php

namespace App\Http\Requests\GroceryList;

// The user's favorite list; without listId the favorite is cleared
class GroceryListFavoriteRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function listId(): ?int
    {
        return $this->input('listId') !== null ? (int) $this->input('listId') : null;
    }
}
