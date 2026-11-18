<?php

namespace App\Http\Requests\GroceryListItem;

use App\Http\Requests\GroceryList\ListFormRequest;

// Deleting several products at once; only those on lists the user may see are deleted
class GroceryListItemDeleteManyRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function itemIds(): array
    {
        return $this->validated('ids');
    }
}
