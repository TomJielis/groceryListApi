<?php

namespace App\Queries\Admin;

use App\Models\Cookbook;
use App\Models\Recipe;
use Carbon\Carbon;

// Recipes and cookbooks: added in the month, and how many there are by its end
class RecipeTotalsQuery
{
    /**
     * @return array{added: int, cookbooks_created: int, total_recipes: int, total_cookbooks: int, shared_cookbooks: int}
     */
    public function forMonth(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();
        $inMonth = fn ($query) => $query->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month);

        return [
            'added' => $inMonth(Recipe::query())->count(),
            'cookbooks_created' => $inMonth(Cookbook::query())->count(),
            'total_recipes' => Recipe::where('created_at', '<=', $endOfMonth)->count(),
            'total_cookbooks' => Cookbook::where('created_at', '<=', $endOfMonth)->count(),
            'shared_cookbooks' => Cookbook::where('created_at', '<=', $endOfMonth)->has('acceptedInvites')->count(),
        ];
    }
}
