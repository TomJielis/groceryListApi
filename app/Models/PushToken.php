<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    protected $fillable = [
        'user_id',
        'access_token_id',
        'token',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken()
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'access_token_id');
    }
}
