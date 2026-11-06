<?php

namespace App\Services;

use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryListInvitesStatus;
use App\Models\Recipe;
use App\Models\User;
use Carbon\Carbon;

/**
 * A user's recipes and cookbooks in numbers, the same for "My statistics" (UserStatsController) and the admin's page
 * about that user (AdminStatsController::userDetail).
 */
class RecipeStats
{
    /**
     * Their cookbooks (own and shared with them) and the recipes they added in all; in the chosen month and the one
     * before: the recipes added and cookbooks made
     */
    public function forUser(User $user, Carbon $currentMonth, Carbon $previousMonth): array
    {
        $inMonth = fn ($query, Carbon $month) => $query->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month);
        $month = fn (Carbon $month) => [
            'added' => $inMonth(Recipe::where('created_by', $user->id), $month)->count(),
            'cookbooks_created' => $inMonth(Cookbook::where('created_by', $user->id), $month)->count(),
        ];

        return [
            'cookbooks_owned' => Cookbook::where('created_by', $user->id)->count(),
            'cookbooks_shared_with_user' => CookbookInvite::where('user_id', $user->id)
                ->where('status', GroceryListInvitesStatus::ACCEPTED)
                ->count(),
            'recipes_added' => Recipe::where('created_by', $user->id)->count(),
            'recipes_access' => Recipe::visibleTo($user)->count(),
            'current_month' => $month($currentMonth),
            'previous_month' => $month($previousMonth),
        ];
    }
}
