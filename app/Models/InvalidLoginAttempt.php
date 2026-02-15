<?php

namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
class InvalidLoginAttempt extends Authenticatable implements MustVerifyEmail
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'attempted_at',
        'blocked_till',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
