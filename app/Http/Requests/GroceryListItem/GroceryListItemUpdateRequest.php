<?php

namespace App\Http\Requests\GroceryListItem;

use App\Models\GroceryListItem;
use Illuminate\Support\Arr;

// Changing an item's name, quantity or price (in item)
class GroceryListItemUpdateRequest extends GroceryListItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item' => 'sometimes|array',
            'item.name' => 'sometimes|required|string|max:255',
            'item.quantity' => 'sometimes|required|numeric|min:0|max:' . GroceryListItem::MAX_QUANTITY,
            'item.unit_price' => 'sometimes|nullable|numeric|min:0|max:' . GroceryListItem::MAX_PRICE,
        ];
    }

    /**
     * Only what the request contains: the app sends just the fields that were changed, so an edit made with an older
     * copy of the item (someone else changed the price meanwhile) can't wipe that price. A field sent as null does
     * count: that's a price being cleared. The website sends every field. Values as sent.
     *
     * @return array{name?: string, quantity?: mixed, unit_price?: mixed}
     */
    public function changes(): array
    {
        return Arr::only($this->input('item', []), ['name', 'quantity', 'unit_price']);
    }
}
