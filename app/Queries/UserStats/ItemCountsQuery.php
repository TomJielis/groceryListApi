<?php

namespace App\Queries\UserStats;

use App\Models\GroceryListItem;
use Carbon\Carbon;

// How many items (counting their quantity) the user added and checked off on the given lists
class ItemCountsQuery
{
    public function added(int $userId, array $listIds, Carbon $month): int
    {
        return (int) GroceryListItem::whereIn('list_id', $listIds)
            ->where('created_by', $userId)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->sum('quantity');
    }

    // Checked off in the month: the last change is the check, so updated_at/updated_by say when and by whom
    public function checked(int $userId, array $listIds, Carbon $month): int
    {
        return (int) GroceryListItem::whereIn('list_id', $listIds)
            ->where('updated_by', $userId)
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->sum('quantity');
    }

    /**
     * The months ("2026-10") in which the user added something, newest first: the months the app lets them pick
     *
     * @return list<string>
     */
    public function availableMonths(int $userId, array $listIds): array
    {
        // Formatted in PHP rather than with MySQL's DATE_FORMAT, so it also runs on the tests' SQLite database
        return GroceryListItem::whereIn('list_id', $listIds)
            ->where('created_by', $userId)
            ->whereNotNull('created_at')
            ->pluck('created_at')
            ->map(fn ($createdAt) => $createdAt->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }
}
