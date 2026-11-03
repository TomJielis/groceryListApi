<?php

namespace App\Models;

use App\Enums\RecipeCategory;
use Illuminate\Database\Eloquent\Model;

// One kind of meal a recipe is; a recipe has none, one or more of these (Recipe::categories)
class RecipeCategoryLink extends Model
{
    protected $table = 'recipe_categories';

    protected $fillable = ['recipe_id', 'category'];

    protected function casts(): array
    {
        return ['category' => RecipeCategory::class];
    }
}
