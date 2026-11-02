<?php

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Everyone gets a first cookbook when they register (Cookbook::createFirstFor); users from before that who don't
     * own one get it now. Users who already made a cookbook keep what they have.
     */
    public function up(): void
    {
        User::whereDoesntHave('cookbooks')->each(fn (User $user) => Cookbook::createFirstFor($user));
    }

    /**
     * Reverse the migrations.
     * The cookbooks stay: by now they may hold recipes.
     */
    public function down(): void
    {
    }
};
