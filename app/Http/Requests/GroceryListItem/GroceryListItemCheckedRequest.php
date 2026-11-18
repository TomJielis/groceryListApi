<?php

namespace App\Http\Requests\GroceryListItem;

// Checking an item off (or on again); without checked it's unchecked
class GroceryListItemCheckedRequest extends GroceryListItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['checked' => 'sometimes|boolean'];
    }

    // As sent (true, 1, "1", ...): it's stored and answered that way, as it always was
    public function checkedValue(): mixed
    {
        return $this->input('checked', false);
    }
}
