<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Someone in a cookbook (besides its creator), like GroceryListInvites: a row is made when they accept an invite link
// (InviteLinkController::accept). The status is GroceryListInvitesStatus::ACCEPTED.
class CookbookInvite extends Model
{
    protected $fillable = [
        'cookbook_id',
        'user_id',
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
