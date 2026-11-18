<?php

namespace App\Queries\Admin;

use App\Models\GroceryList;
use Carbon\Carbon;

// The ten lists most products were added to in a month
class TopListsQuery
{
    /**
     * @return list<array{id: int, name: string, items_added: int}>
     */
    public function forMonth(Carbon $month): array
    {
        return GroceryList::withoutGlobalScopes()
            ->select('grocery_lists.id', 'grocery_lists.name')
            ->selectRaw('COALESCE(SUM(grocery_list_items.quantity), 0) as items_added')
            ->leftJoin('grocery_list_items', function ($join) use ($month) {
                $join->on('grocery_lists.id', '=', 'grocery_list_items.list_id')
                    ->whereYear('grocery_list_items.created_at', $month->year)
                    ->whereMonth('grocery_list_items.created_at', $month->month);
            })
            ->groupBy('grocery_lists.id', 'grocery_lists.name')
            ->orderByDesc('items_added')
            ->limit(10)
            ->get()
            ->map(fn ($list) => ['id' => $list->id, 'name' => $list->name, 'items_added' => (int) $list->items_added])
            ->toArray();
    }
}
