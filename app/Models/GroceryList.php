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
        return $this->hasMany(GroceryListInvites::class, 'grocery_list_id', 'id');
    }
}
