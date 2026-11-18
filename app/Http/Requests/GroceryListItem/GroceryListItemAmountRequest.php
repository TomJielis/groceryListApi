<?php

namespace App\Http\Requests\GroceryListItem;

// One more or less of an item, or amount more or less
class GroceryListItemAmountRequest extends GroceryListItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['amount' => 'sometimes|integer|min:1|max:1000'];
    }

    public function amount(): int
    {
        return (int) $this->input('amount', 1);
    }
}
