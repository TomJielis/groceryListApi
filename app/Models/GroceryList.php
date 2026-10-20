<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroceryList extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grocery_lists';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('user', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->where('grocery_lists.created_by', auth()->id());

            $builder->orWhereHas('groceryListInvites', function ($query) {
                $query->where('user_id', auth()->id())
                      ->where('status', GroceryListInvitesStatus::ACCEPTED);
            });
        });
    }


    public function groceryListItems()
    {
        return $this->hasMany(GroceryListItem::class, 'list_id', 'id');
    }

    public function groceryListItemsChecked()
    {
        return $this->groceryListItems()->where('checked', true);
    }

    public function groceryListInvites()
    {
        return $this->hasMany(GroceryListInvites::class, 'grocery_list_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Ids of everyone on the list: the owner plus users who accepted an invite
     *
     * @return list<int>
     */
    public function memberIds(): array
    {
        $invitedIds = $this->groceryListInvites()
            ->where('status', GroceryListInvitesStatus::ACCEPTED)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return $invitedIds->push($this->created_by)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Whether something still needs to be bought. Items are versioned per name (the latest row is the current
     * one, like GroceryListItemController::index), so only the latest row of each name counts.
     */
    public function hasOpenItems(): bool
    {
        $latestIds = GroceryListItem::selectRaw('MAX(id)')
            ->where('list_id', $this->id)
            ->groupBy('name');

        return GroceryListItem::whereIn('id', $latestIds)->where('checked', false)->exists();
    }
}
