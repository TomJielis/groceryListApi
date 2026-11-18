<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroceryListItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grocery_list_items';

    // The most a product's quantity and price can be (adding and changing an item)
    public const MAX_QUANTITY = 100000;
    public const MAX_PRICE = 1000000;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $userId = auth()->id();
            if ($userId) {
                $model->created_by = $userId;
                $model->updated_by = $userId;
            }
        });

        static::updating(function ($model) {
            $userId = auth()->id();
            if ($userId) {
                $model->updated_by = $userId;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'quantity',
        'list_id',
        'checked',
        'unit_price',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the user who created the item.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the item.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
