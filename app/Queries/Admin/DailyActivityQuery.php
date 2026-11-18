<?php

namespace App\Queries\Admin;

use App\Models\GroceryListItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Products added and checked per day of a month, up to today
class DailyActivityQuery
{
    /**
     * @return list<array{date: string, items_added: int|string, items_checked: int|string}>
     */
    public function forMonth(Carbon $month): array
    {
        $dailyAdded = GroceryListItem::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(quantity) as items_added'))
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $dailyChecked = GroceryListItem::select(DB::raw('DATE(updated_at) as date'), DB::raw('SUM(quantity) as items_checked'))
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $endOfMonth = $month->copy()->endOfMonth();
        $today = Carbon::now();
        for ($day = $month->copy()->startOfMonth(); $day <= $endOfMonth && $day <= $today; $day->addDay()) {
            $date = $day->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'items_added' => $dailyAdded->get($date)?->items_added ?? 0,
                'items_checked' => $dailyChecked->get($date)?->items_checked ?? 0,
            ];
        }

        return $result;
    }
}
