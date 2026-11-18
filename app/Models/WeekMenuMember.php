<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Someone in a week menu besides its creator: made when they accept an invite link (InviteLinkController::accept)
class WeekMenuMember extends Model
{
    protected $fillable = [
        'week_menu_id',
        'user_id',
    ];

    public function weekMenu(): BelongsTo
    {
        return $this->belongsTo(WeekMenu::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
