<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// A collection of recipes, like a grocery list holds products: it belongs to whoever made it (everyone gets a first one
// when they register, createFirstFor), and sharing it lets others see and edit its recipes. Recipes of other cookbooks can also be shown in it
// (shownRecipes), read-only. Like a list, only the creator renames it or decides who is in it, and when they
// delete it (or their account) it's gone for everyone.
class Cookbook extends Model
{
    protected $fillable = [
        'name',
        'created_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    // Recipes of other cookbooks shown in this one (read-only here)
    public function shownRecipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'cookbook_recipe')->withPivot('shown_by')->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CookbookInvite::class);
    }

    public function acceptedInvites(): HasMany
    {
        return $this->invites()->where('status', GroceryListInvitesStatus::ACCEPTED);
    }

    /**
     * The cookbooks the user may see, like GroceryList's global scope: their own and the ones they accepted an
     * invite for
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('cookbooks.created_by', $user->id)
            ->orWhereHas('acceptedInvites', fn ($invites) => $invites->where('user_id', $user->id)));
    }

    // "Mijn kookboek" / "My cookbook", in the user's language: so a new user's own cookbook is there, and first, before
    // any shared with them. It's their favourite too (new recipes from "all recipes" go into it), unless they chose one.
    public static function createFirstFor(User $user): self
    {
        $cookbook = self::create([
            'name' => __('recipes.first_cookbook', [], $user->language === 'en' ? 'en' : 'nl'),
            'created_by' => $user->id,
        ]);
        if ($user->favorite_cookbook_id === null) {
            $user->favorite_cookbook_id = $cookbook->id;
            $user->save();
        }

        return $cookbook;
    }

    public function isCreatedBy(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id;
    }

    /**
     * Ids of everyone in the cookbook: the creator plus users who accepted an invite
     *
     * @return list<int>
     */
    public function memberIds(): array
    {
        $invitedIds = $this->acceptedInvites()->whereNotNull('user_id')->pluck('user_id');

        return $invitedIds->push($this->created_by)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Someone leaving the cookbook (or removed from it) takes back what they showed: its recipes they showed in other
     * cookbooks, and the recipes they showed in it. Otherwise they, and whoever is in their other cookbooks, would
     * keep seeing recipes of a cookbook they're no longer in.
     */
    public function withdrawShownBy(int $userId): void
    {
        DB::table('cookbook_recipe')
            ->where('shown_by', $userId)
            ->where(fn ($query) => $query
                ->where('cookbook_id', $this->id)
                ->orWhereIn('recipe_id', Recipe::where('cookbook_id', $this->id)->select('id')))
            ->delete();
    }

    // Everything in it: the recipes with their photos (ingredients, steps and favorites go with them through their
    // foreign keys), the invites, and it as someone's favorite. The photos are removed once that's saved.
    public function deleteWithRecipes(): void
    {
        $imagePaths = $this->recipes()->whereNotNull('image_path')->pluck('image_path');

        DB::transaction(function () {
            $this->recipes()->delete();
            $this->invites()->delete();
            $this->delete();
        });

        foreach ($imagePaths as $imagePath) {
            Storage::disk(Recipe::IMAGE_DISK)->delete([$imagePath, Recipe::thumbPath($imagePath)]);
        }
    }
}
