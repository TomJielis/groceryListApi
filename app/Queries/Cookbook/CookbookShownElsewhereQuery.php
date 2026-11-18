<?php

namespace App\Queries\Cookbook;

use Illuminate\Support\Facades\DB;

/**
 * Per cookbook: how many of its own recipes are shown in other cookbooks, and the names of those, for the warning when
 * its creator deletes it (the recipes go there too). In one query for all the cookbooks of the overview.
 */
class CookbookShownElsewhereQuery
{
    /**
     * @param list<int> $cookbookIds
     * @return array<int, array{recipes: int, cookbooks: list<string>}>
     */
    public function forCookbooks(array $cookbookIds): array
    {
        return DB::table('cookbook_recipe')
            ->join('recipes', 'recipes.id', '=', 'cookbook_recipe.recipe_id')
            ->join('cookbooks', 'cookbooks.id', '=', 'cookbook_recipe.cookbook_id')
            ->whereIn('recipes.cookbook_id', $cookbookIds)
            ->orderBy('cookbooks.id')
            ->get(['recipes.cookbook_id as home_id', 'cookbook_recipe.recipe_id', 'cookbooks.name'])
            ->groupBy('home_id')
            ->map(fn ($rows) => [
                'recipes' => $rows->pluck('recipe_id')->unique()->count(),
                'cookbooks' => $rows->pluck('name')->unique()->values()->all(),
            ])
            ->all();
    }
}
