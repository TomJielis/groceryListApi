<?php

namespace App\Queries\Items;

use App\Models\GroceryListItem;
use Carbon\Carbon;

// What the checked products with a price cost in a month (quantity times price): on all lists, or on the given ones
class SpendQuery
{
    /**
     * @param iterable<int>|null $listIds
     */
    public function total(Carbon $month, ?iterable $listIds = null): float
    {
        $total = GroceryListItem::selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->when($listIds !== null, fn ($query) => $query->whereIn('list_id', $listIds))
            ->where('checked', true)
            ->whereNotNull('unit_price')
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->value('total');

        return round((float) $total, 2);
    }

    /**
     * Per list, most spent first
     *
     * @param list<int> $listIds
     * @return list<array{id: int, name: string, total: float}>
     */
    public function byList(Carbon $month, array $listIds): array
    {
        if (empty($listIds)) {
            return [];
        }

        return GroceryListItem::selectRaw('list_id, grocery_lists.name, COALESCE(SUM(quantity * unit_price), 0) as total')
            ->join('grocery_lists', 'grocery_lists.id', '=', 'grocery_list_items.list_id')
            ->whereIn('grocery_list_items.list_id', $listIds)
            ->where('checked', true)
            ->whereNotNull('unit_price')
            ->whereYear('grocery_list_items.updated_at', $month->year)
            ->whereMonth('grocery_list_items.updated_at', $month->month)
            ->groupBy('list_id', 'grocery_lists.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($item) => [
                'id'    => $item->list_id,
                'name'  => $item->name,
                'total' => round((float) $item->total, 2),
            ])
            ->toArray();
    }
}
