<?php

namespace App\Http\Requests\GroceryListItem;

use App\Http\Requests\GroceryList\ListFormRequest;
use App\Models\GroceryListItem;

/**
 * Adding a product to a list. The list is checked before the fields (authorize runs before validation), so a list the
 * user may not see answers 403 even when the fields are wrong too, as it always did.
 */
class GroceryListItemStoreRequest extends ListFormRequest
{
    public function authorize(): bool
    {
        $this->ensureListAccess($this->listId());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'quantity' => 'sometimes|numeric|min:0|max:' . GroceryListItem::MAX_QUANTITY,
        ];
    }

    // As sent (not validated): the item answered echoes it the way it came in
    public function listId(): mixed
    {
        return $this->input('list_id');
    }

    public function itemName(): string
    {
        return $this->validated('name');
    }

    // As sent, 1 when not sent; the item answered echoes it the way it came in
    public function quantity(): mixed
    {
        return $this->input('quantity') ?? 1;
    }
}
