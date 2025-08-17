<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroceryListInvitesStatus extends Model
{
    const PENDING = 'pending';
    const ACCEPTED = 'accepted';
    const DECLINED = 'declined';
}
