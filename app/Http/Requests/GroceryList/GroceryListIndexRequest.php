<?php

namespace App\Http\Requests\GroceryList;

// The lists the user may see; ?from=&till= is an offset and a limit (both or neither)
class GroceryListIndexRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * [offset, limit] as sent (the query builder casts them), or null for everything
     *
     * @return array{0: mixed, 1: mixed}|null
     */
    public function page(): ?array
    {
        $offset = $this->input('from');
        $limit = $this->input('till');

        return isset($offset) && isset($limit) ? [$offset, $limit] : null;
    }
}
