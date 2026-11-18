<?php

namespace App\Queries\UserStats;

use App\Queries\Items\SpendQuery;
use App\Queries\Items\TopItemsQuery;
use App\Data\UserStats;
use App\Data\UserStatsMonth;
use App\Models\User;
use App\Queries\Recipes\RecipeStatsQuery;
use Carbon\Carbon;

// "My statistics": every figure of the chosen month and the one before, on the user's own and shared lists
class UserStatsQuery
{
    public function __construct(
        private AccessibleListsQuery $lists,
        private ItemCountsQuery $items,
        private SpendQuery $spend,
        private UserBreakdownQuery $breakdown,
        private TopItemsQuery $topItems,
        private RecipeStatsQuery $recipeStats,
    ) {
    }

    public function forUser(User $user, Carbon $month): UserStats
    {
        $previousMonth = $month->copy()->subMonth();
        $listIds = $this->lists->idsFor($user);

        return new UserStats(
            current: $this->month($user, $listIds, $month),
            previous: $this->month($user, $listIds, $previousMonth),
            mostAdded: $this->topItems->mostAdded($month, $listIds, $user->id, withListsCount: false),
            mostChecked: $this->topItems->mostChecked($month, $listIds, $user->id),
            recipes: $this->recipeStats->forUser($user, $month, $previousMonth),
            availableMonths: $this->items->availableMonths($user->id, $listIds),
        );
    }

    private function month(User $user, array $listIds, Carbon $month): UserStatsMonth
    {
        return new UserStatsMonth(
            month: $month,
            added: $this->items->added($user->id, $listIds, $month),
            checked: $this->items->checked($user->id, $listIds, $month),
            spendTotal: $this->spend->total($month, $listIds),
            spendByList: $this->spend->byList($month, $listIds),
            userBreakdown: $this->breakdown->forMonth($listIds, $month),
            invalidLoginAttempts: $user->invalidLoginAttempts()
                ->where('attempted_at', '>=', $month->copy()->startOfMonth())
                ->where('attempted_at', '<=', $month->copy()->endOfMonth())
                ->count(),
        );
    }
}
