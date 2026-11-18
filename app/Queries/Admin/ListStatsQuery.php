<?php

namespace App\Queries\Admin;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use Carbon\Carbon;

// Lists made in a month, and by its end: how many are shared, products per list and people per shared list
class ListStatsQuery
{
    /**
     * @return array{created: int, shared: int, avg_items_per_list: float|int, avg_members_per_shared_list: float|int}
     */
    public function forMonth(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();
        $lists = fn () => GroceryList::withoutGlobalScopes();

        $created = $lists()->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count();

        $sharedListIds = $lists()
            ->whereHas('groceryListInvites', fn ($query) => $query->where('status', GroceryListInvitesStatus::ACCEPTED))
            ->where('created_at', '<=', $endOfMonth)
            ->pluck('id');

        $totalItems = GroceryListItem::where('created_at', '<=', $endOfMonth)->count();
        $totalLists = $lists()->where('created_at', '<=', $endOfMonth)->count();

        // The people a shared list is shared with, and its creator
        $totalMembers = 0;
        if ($sharedListIds->count() > 0) {
            $totalMembers = GroceryListInvites::whereIn('grocery_list_id', $sharedListIds)
                ->where('status', GroceryListInvitesStatus::ACCEPTED)
                ->count() + $sharedListIds->count();
        }

        return [
            'created' => $created,
            'shared' => $sharedListIds->count(),
            'avg_items_per_list' => $totalLists > 0 ? round($totalItems / $totalLists, 1) : 0,
            'avg_members_per_shared_list' => $sharedListIds->count() > 0 ? round($totalMembers / $sharedListIds->count(), 1) : 0,
        ];
    }
}
