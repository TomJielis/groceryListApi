<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroceryListInvites extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grocery_list_invites';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grocery_list_id',
        'user_id',
        'status'
    ];

    public function groceryList()
    {
        return $this->belongsTo(GroceryList::class, 'grocery_list_id', 'id');
    }
}
