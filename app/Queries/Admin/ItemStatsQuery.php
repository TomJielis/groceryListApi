<?php

namespace App\Queries\Admin;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

// Products added and checked in a month, and the averages per active user and per list
class ItemStatsQuery
{
    /**
     * @return array{added: int, checked: int, avg_per_user: float|int, avg_per_list: float|int}
     */
    public function forMonth(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();

        $added = (int) GroceryListItem::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->sum('quantity');

        $checked = (int) GroceryListItem::where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->sum('quantity');

        $activeUserCount = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('last_used_at', '>=', $endOfMonth->copy()->subDays(30))
            ->where('last_used_at', '<=', $endOfMonth)
            ->distinct()
            ->pluck('tokenable_id')
            ->count();

        $listCount = GroceryList::withoutGlobalScopes()->where('created_at', '<=', $endOfMonth)->count();

        return [
            'added' => $added,
            'checked' => $checked,
            'avg_per_user' => $activeUserCount > 0 ? round($added / $activeUserCount, 1) : 0,
            'avg_per_list' => $listCount > 0 ? round($added / $listCount, 1) : 0,
        ];
    }
}
