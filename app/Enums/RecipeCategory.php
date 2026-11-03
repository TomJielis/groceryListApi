<?php

namespace App\Enums;

// The kinds of meal a recipe can be: none, one or more of them (recipe_categories). Also an enum column there, so the
// database takes nothing else. A new kind needs a migration that adds it to that column.
enum RecipeCategory: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Main = 'main';
    case Side = 'side';
    case Snack = 'snack';
    case Dessert = 'dessert';

    // What app versions from before categories know: they send and show one of these (Recipe::category).
    // LEGACY(category): for app builds before 60 (one category). Remove once nobody uses them.
    public const LEGACY = [self::Breakfast, self::Lunch, self::Main, self::Side, self::Dessert];

    /** @return list<string> In this order: how the app lists them */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
