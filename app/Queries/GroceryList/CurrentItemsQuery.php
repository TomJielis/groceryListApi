<?php

namespace App\Queries\GroceryList;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Database\Eloquent\Collection;

// The current items of the lists the user may see: items are versioned per name, the latest row of a name is the item
class CurrentItemsQuery
{
    /**
     * @param mixed $listId Only this list (as sent), or null for all of them
     * @param array{0: mixed, 1: mixed}|null $page [offset, limit], or null for every item
     * @return Collection<int, GroceryListItem>
     */
    public function get(mixed $listId, ?array $page): Collection
    {
        // The lists the user may see (own and accepted shares; GroceryList's global scope), narrowed down first so
        // the grouping below only touches their items, not those of every user
        $listIds = GroceryList::query()->pluck('id');
        if (isset($listId)) {
            $listIds = $listIds->filter(fn ($id) => (int) $id === (int) $listId)->values();
        }

        // The latest item per name in each list
        $latestIds = GroceryListItem::selectRaw('MAX(id) as id')
            ->whereIn('list_id', $listIds)
            ->groupBy('list_id', 'name');

        $query = GroceryListItem::select('grocery_list_items.*')
            ->joinSub($latestIds, 'latest', 'grocery_list_items.id', '=', 'latest.id')
            ->orderBy('grocery_list_items.id');

        if ($page !== null) {
            [$offset, $limit] = $page;
            $query->limit($limit)->offset($offset);
        }

        return $query->get();
    }
}
