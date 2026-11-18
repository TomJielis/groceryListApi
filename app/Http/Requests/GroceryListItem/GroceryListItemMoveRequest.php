<?php

namespace App\Http\Requests\GroceryListItem;

use App\Http\Requests\GroceryList\ListFormRequest;

// Moving products to another list of the user's. Here the fields are checked first and the list after (422 before 403).
class GroceryListItemMoveRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'list_id' => 'required|integer',
        ];
    }

    protected function passedValidation(): void
    {
        $this->ensureListAccess($this->validated('list_id'));
    }

    /**
     * @return array<int, mixed>
     */
    public function itemIds(): array
    {
        return $this->validated('ids');
    }

    public function targetListId(): int
    {
        return (int) $this->validated('list_id');
    }
}
