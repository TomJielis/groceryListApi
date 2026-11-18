<?php

namespace App\Queries\Admin;

use App\Data\StatsMonths;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use App\Queries\Items\SpendQuery;
use App\Queries\Items\TopItemsQuery;
use App\Queries\Recipes\RecipeStatsQuery;
use Carbon\Carbon;

// One user in numbers, for the admin's page about them (AdminUserDetailResource shows it)
class UserActivityQuery
{
    public function __construct(
        private TopItemsQuery $topItems,
        private SpendQuery $spend,
        private RecipeStatsQuery $recipeStats,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, StatsMonths $months): array
    {
        $ownedListIds = GroceryList::withoutGlobalScopes()->where('created_by', $user->id)->pluck('id');
        $sharedListIds = GroceryListInvites::where('user_id', $user->id)
            ->where('status', GroceryListInvitesStatus::ACCEPTED)
            ->pluck('grocery_list_id');
        $listIds = $ownedListIds->merge($sharedListIds)->unique();

        // On their lists: what they added and what they checked
        $added = fn (Carbon $month) => (int) GroceryListItem::whereIn('list_id', $listIds)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->where('created_by', $user->id)
            ->sum('quantity');
        $checked = fn (Carbon $month) => (int) GroceryListItem::whereIn('list_id', $listIds)
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->where('updated_by', $user->id)
            ->sum('quantity');
        $topItems = fn (Carbon $month) => [
            'most_added' => $this->topItems->mostAdded($month, $listIds->toArray(), $user->id),
            'most_checked' => $this->topItems->mostChecked($month, $listIds->toArray(), $user->id),
        ];
        $invalidLogins = fn (Carbon $month) => $user->invalidLoginAttempts()
            ->where('attempted_at', '>=', $month->copy()->startOfMonth())
            ->where('attempted_at', '<=', $month->copy()->endOfMonth())
            ->count();

        return [
            'lists' => [
                'owned' => $ownedListIds->count(),
                'shared_with_user' => $sharedListIds->count(),
                'total_access' => $listIds->count(),
            ],
            'recipes' => $this->recipeStats->forUser($user, $months->current, $months->previous),
            'items' => $months->compare(fn (Carbon $month) => ['added' => $added($month), 'checked' => $checked($month)]),
            'top_items' => [
                'current_month' => $topItems($months->current),
                'previous_month' => $topItems($months->previous),
            ],
            'spend' => [
                'current_month' => ['total' => $this->spend->total($months->current, $listIds)],
                'previous_month' => ['total' => $this->spend->total($months->previous, $listIds)],
            ],
            'invalid_loggin_attempts' => [
                'current_month' => $invalidLogins($months->current),
                'previous_month' => $invalidLogins($months->previous),
            ],
        ];
    }
}
