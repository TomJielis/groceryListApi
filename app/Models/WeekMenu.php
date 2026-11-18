<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Recipes planned per date, shared by invite link like a cookbook (see the week_menus migration). Everyone in it plans
// in it; only the creator renames it, removes people and deletes it (for everyone).
class WeekMenu extends Model
{
    // The longest stretch of planned days asked for at once: a month view, with the days around it
    public const MAX_DAYS = 62;

    // What WeekMenuResource shows
    public const PRESENTED_WITH = ['createdBy:id,name', 'members.user:id,name'];

    protected $fillable = [
        'name',
        'created_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WeekMenuMember::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WeekMenuEntry::class);
    }

    // The menus the user is in: their own and the ones they joined
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('week_menus.created_by', $user->id)
            ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)));
    }

    public function isCreatedBy(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id;
    }

    /**
     * Ids of everyone in it: the creator plus the members
     *
     * @return list<int>
     */
    public function memberIds(): array
    {
        return $this->members()->pluck('user_id')
            ->push($this->created_by)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
