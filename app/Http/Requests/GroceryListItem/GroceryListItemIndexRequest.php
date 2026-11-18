<?php

namespace App\Http\Requests\GroceryListItem;

use App\Http\Requests\GroceryList\GroceryListIndexRequest;

// The current items of the lists the user may see, or of one list (listId); from/till as for the lists
class GroceryListItemIndexRequest extends GroceryListIndexRequest
{
    // As sent; a list the user may not see simply has no items here
    public function listId(): mixed
    {
        return $this->input('listId');
    }
}
