<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A recipe planned on a day of a week menu, for a number of servings
class WeekMenuEntry extends Model
{
    public const MAX_SERVINGS = 99;

    // A calendar date, within reason
    public const DATE_RULES = 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before:2100-01-01';

    // Not (or no longer) put on a list: the next "put on my list" takes it along
    public const NOT_ADDED = [
        'added_to_list_at' => null,
        'added_to_list_by' => null,
        'added_to_list_id' => null,
        'added_to_list_servings' => null,
    ];

    // What WeekMenuEntryResource shows
    public const PRESENTED_WITH = ['recipe.ingredients', 'addedBy:id,name', 'addedToListBy:id,name', 'addedToList'];

    protected $fillable = [
        'week_menu_id',
        'date',
        'recipe_id',
        'servings',
        'position',
        'added_by',
        'added_to_list_at',
        'added_to_list_by',
        'added_to_list_id',
        'added_to_list_servings',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'servings' => 'integer',
            'position' => 'integer',
            'added_to_list_at' => 'datetime',
            'added_to_list_servings' => 'integer',
        ];
    }

    public function weekMenu(): BelongsTo
    {
        return $this->belongsTo(WeekMenu::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'id');
    }

    public function addedToListBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_to_list_by', 'id');
    }

    // The list its ingredients went on (any of the user's: not limited to the signed-in user's lists)
    public function addedToList(): BelongsTo
    {
        return $this->belongsTo(GroceryList::class, 'added_to_list_id')->withoutGlobalScopes();
    }
}
