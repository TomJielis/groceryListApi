<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'language',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function groceryLists()
    {
        return $this->hasMany(GroceryList::class, 'created_by', 'id');
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function groceryListInvites()
    {
        return $this->hasMany(GroceryListInvites::class, 'user_id', 'id');
    }

    public function personalAccessTokens()
    {
        return $this->hasMany(\Laravel\Sanctum\PersonalAccessToken::class, 'tokenable_id', 'id')->where('tokenable_type', self::class);
    }

    public function temporaryPasswordCodes()
    {
        return $this->hasMany(TemporaryPasswordCode::class, 'user_id', 'id');
    }

    public function invalidLoginAttempts()
    {
        return $this->hasMany(InvalidLoginAttempt::class, 'user_id', 'id');
    }
}
