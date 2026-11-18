<?php

namespace App\Http\Requests\GroceryListItem;

use App\Http\Requests\GroceryList\ListFormRequest;
use App\Models\GroceryListItem;

/**
 * Something done to one item ({listItem} in the route), only on a list the user may see. Checked before the fields
 * (authorize runs before validation), as it always was. Without fields of its own it's the delete.
 */
class GroceryListItemRequest extends ListFormRequest
{
    public function authorize(): bool
    {
        $this->ensureListAccess($this->listItem()->list_id);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function listItem(): GroceryListItem
    {
        return $this->route('listItem');
    }
}
