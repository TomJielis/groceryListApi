<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// The status of a row in grocery_list_invites / cookbook_invites. Since sharing is only by invite link, every row is a
// member who accepted (the pending and declined email invites are gone).
class GroceryListInvitesStatus extends Model
{
    const ACCEPTED = 'accepted';
}
