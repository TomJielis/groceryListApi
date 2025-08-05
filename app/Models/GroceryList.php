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


    public function groceryListItems()
    {
        return $this->hasMany(GroceryListItem::class, 'list_id', 'id');
    }

    public function groceryListItemsChecked()
    {
        return $this->groceryListItems()->where('checked', true);
    }
}
