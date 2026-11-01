<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// A collection of recipes, like a grocery list holds products: it belongs to whoever made it (nobody gets one without
// making it), and sharing it lets others see and edit its recipes. Like a list, only the creator renames it or decides who is in it, and when they
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

    public function invites(): HasMany
    {
        return $this->hasMany(CookbookInvite::class);
    }

    public function acceptedInvites(): HasMany
    {
        return $this->invites()->where('status', GroceryListInvitesStatus::ACCEPTED);
    }

    public function pendingInvites(): HasMany
    {
        return $this->invites()->where('status', GroceryListInvitesStatus::PENDING);
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
