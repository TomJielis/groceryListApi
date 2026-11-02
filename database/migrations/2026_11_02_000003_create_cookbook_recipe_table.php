<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * The cookbooks a recipe is also shown in, besides its own (recipes.cookbook_id). There it's read-only: only the
     * people in the recipe's own cookbook edit it, so e.g. a family cookbook can show a couple's recipes without the
     * family changing them. Gone with the recipe or the cookbook, and with who showed it there (shown_by): someone
     * leaving a cookbook takes back what they showed (Cookbook::withdrawShownBy).
     */
    public function up(): void
    {
        Schema::create('cookbook_recipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookbook_id')->constrained('cookbooks')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('shown_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cookbook_id', 'recipe_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cookbook_recipe');
    }
};
