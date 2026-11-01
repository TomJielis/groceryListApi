<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// An invite to a cookbook, like GroceryListInvites: by email, so an address without an account can be invited too.
// The status is one of GroceryListInvitesStatus.
class CookbookInvite extends Model
{
    protected $fillable = [
        'cookbook_id',
        'user_id',
        'email',
        'status',
    ];

    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
