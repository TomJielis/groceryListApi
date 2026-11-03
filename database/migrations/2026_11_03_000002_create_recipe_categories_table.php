<?php

use App\Enums\RecipeCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A recipe is none, one or more kinds of meal (breakfast and lunch, say; a snack is one now): one row per kind, in
// place of the one category it had. Each recipe's category becomes its first row, so nothing is lost. The column is
// an enum: the database takes no other value. App versions from before still get a category, worked out from these
// (Recipe::category).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->enum('category', RecipeCategory::values());
            $table->timestamps();
            $table->unique(['recipe_id', 'category']);
        });

        DB::table('recipes')->orderBy('id')->select(['id', 'category'])->chunk(500, function ($recipes) {
            DB::table('recipe_categories')->insert($recipes->map(fn ($recipe) => [
                'recipe_id' => $recipe->id,
                'category' => $recipe->category,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('category', 20)->default('main')->after('description');
        });

        // Back to one: the first kind of meal of each recipe (a main course when it had none)
        DB::table('recipe_categories')->orderBy('id')->get()->groupBy('recipe_id')->each(function ($rows, $recipeId) {
            DB::table('recipes')->where('id', $recipeId)->update(['category' => $rows->first()->category]);
        });

        Schema::dropIfExists('recipe_categories');
    }
};
