<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recipes live in a cookbook, like products in a list: it belongs to whoever made it, and like a list it can be
        // shared, after which everyone in it sees and edits its recipes. A first one is made the first time someone
        // uses recipes, so it works without setting anything up.
        Schema::create('cookbooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Who it's shared with, like grocery_list_invites: by email, so someone without an account can be invited
        // too. An accepted invite is what makes someone a member.
        Schema::create('cookbook_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookbook_id')->constrained('cookbooks')->cascadeOnDelete();
            // Filled in once the address has an account
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email');
            // pending, accepted or declined (GroceryListInvitesStatus)
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookbook_id')->constrained('cookbooks')->cascadeOnDelete();
            // Who added it (shown in a shared cookbook); empty once a member who added it deleted their account
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // breakfast, lunch, main, side or dessert (the app shows them translated)
            $table->string('category', 20);
            $table->unsignedSmallInteger('servings');
            // Hands-on time, time on the stove and time in the oven, in minutes
            $table->unsignedSmallInteger('prep_minutes')->default(0);
            $table->unsignedSmallInteger('cook_minutes')->nullable();
            $table->unsignedSmallInteger('oven_minutes')->nullable();
            $table->unsignedSmallInteger('oven_celsius')->nullable();
            // easy, medium or hard
            $table->string('difficulty', 20);
            // Per serving, when known
            $table->unsignedSmallInteger('kcal')->nullable();
            $table->unsignedSmallInteger('protein')->nullable();
            $table->unsignedSmallInteger('carbs')->nullable();
            $table->unsignedSmallInteger('fat')->nullable();
            // The page it was imported from
            $table->string('source_url', 2048)->nullable();
            // The photo on the private disk (served only to the cookbook's members)
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['cookbook_id', 'created_at']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            // Empty for "to taste" (salt and pepper)
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('unit', 30)->default('');

            $table->index(['recipe_id', 'position']);
        });

        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('body');

            $table->index(['recipe_id', 'position']);
        });

        // Favorites are personal: a star in a shared cookbook doesn't change it for the others
        Schema::create('recipe_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['recipe_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_favorites');
        Schema::dropIfExists('recipe_steps');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('cookbook_invites');
        Schema::dropIfExists('cookbooks');
    }
};
