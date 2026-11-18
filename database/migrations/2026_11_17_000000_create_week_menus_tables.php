<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Week menus: recipes planned per date ("Monday lasagne, Tuesday chicken teriyaki"), whose ingredients go on a list
     * in one go. Someone makes one by hand (not everyone uses it), and shares it by invite link like a cookbook: the
     * members plan in it together. A recipe planned in a menu can be opened by everyone in it (Recipe::readableBy),
     * without being in their recipe book. Only new tables and a nullable column: older apps keep working.
     */
    public function up(): void
    {
        Schema::create('week_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Gone with its creator's account, and with it everything in it
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Everyone in a menu besides its creator: a row is made when they accept an invite link
        Schema::create('week_menu_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_menu_id')->constrained('week_menus')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['week_menu_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('week_menu_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_menu_id')->constrained('week_menus')->cascadeOnDelete();
            // The day it's planned for (a calendar date, no time: the same day for everyone in the menu)
            $table->date('date');
            // Gone from the menu when the recipe is deleted
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedSmallInteger('servings');
            $table->unsignedSmallInteger('position')->default(0);
            // Who planned it ("Lasagne · by Tom"); kept when they leave or delete their account
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            // When its ingredients were put on a list, by whom, on which list and for how many servings: the dish gets a
            // check, the next "put on my list" leaves it out, and "servings changed" shows when that number changed
            $table->timestamp('added_to_list_at')->nullable();
            $table->foreignId('added_to_list_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_to_list_id')->nullable()->constrained('grocery_lists')->nullOnDelete();
            $table->unsignedSmallInteger('added_to_list_servings')->nullable();
            $table->timestamps();

            $table->index(['week_menu_id', 'date']);
            $table->index('recipe_id');
        });

        Schema::table('invite_links', function (Blueprint $table) {
            $table->foreignId('week_menu_id')->nullable()->after('cookbook_id')->constrained('week_menus')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invite_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('week_menu_id');
        });
        Schema::dropIfExists('week_menu_entries');
        Schema::dropIfExists('week_menu_members');
        Schema::dropIfExists('week_menus');
    }
};
