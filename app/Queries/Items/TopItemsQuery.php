<?php

namespace App\Queries\Items;

use App\Models\GroceryListItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The products added and checked most in a month, names counted together whatever their case: everyone's (top 10, the
 * admin dashboard), or on the given lists and by one user (top 5: "My statistics" and the admin's page about a user)
 */
class TopItemsQuery
{
    /**
     * With withListsCount, also on how many lists each was added
     *
     * @param list<int>|null $listIds
     * @return list<array{name: string, count: int, lists_count?: int}>
     */
    public function mostAdded(Carbon $month, ?array $listIds = null, ?int $userId = null, bool $withListsCount = true): array
    {
        $query = GroceryListItem::selectRaw('LOWER(name) as name, SUM(quantity) as count' . ($withListsCount ? ', COUNT(DISTINCT list_id) as lists_count' : ''))
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->when($userId, fn ($query) => $query->where('created_by', $userId));

        return $this->top($query, $listIds)
            ->map(fn ($item) => [
                'name' => ucfirst($item->name),
                'count' => (int) $item->count,
                ...($withListsCount ? ['lists_count' => (int) $item->lists_count] : []),
            ])
            ->toArray();
    }

    /**
     * @param list<int>|null $listIds
     * @return list<array{name: string, count: int}>
     */
    public function mostChecked(Carbon $month, ?array $listIds = null, ?int $userId = null): array
    {
        $query = GroceryListItem::selectRaw('LOWER(name) as name, SUM(quantity) as count')
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->when($userId, fn ($query) => $query->where('updated_by', $userId));

        return $this->top($query, $listIds)
            ->map(fn ($item) => ['name' => ucfirst($item->name), 'count' => (int) $item->count])
            ->toArray();
    }

    private function top(Builder $query, ?array $listIds)
    {
        return $query->when($listIds !== null, fn ($query) => $query->whereIn('list_id', $listIds))
            ->groupBy(DB::raw('LOWER(name)'))
            ->orderByDesc('count')
            ->limit($listIds !== null ? 5 : 10)
            ->get();
    }
}
