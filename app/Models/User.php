<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
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
            'notification_settings' => 'array',
        ];
    }

    /**
     * Push notification settings a user can switch off in the app, and the push types each one covers
     * (types are the keys in resources/lang/{nl,en}/push.php).
     */
    public const NOTIFICATION_SETTINGS = [
        'invites' => ['list_shared', 'invite_accepted'],
        'list_changes' => ['list_removed', 'list_deleted'],
        'items_added' => ['items_added'],
        'items_checked' => ['items_checked'],
        'list_completed' => ['list_completed'],
    ];

    /**
     * Every setting with its current value; everything is on unless the user switched it off
     *
     * @return array<string, bool>
     */
    public function notificationSettings(): array
    {
        $stored = $this->notification_settings ?? [];

        return collect(self::NOTIFICATION_SETTINGS)
            ->map(fn ($types, string $setting) => (bool) ($stored[$setting] ?? true))
            ->all();
    }

    public function wantsPush(string $type): bool
    {
        foreach (self::NOTIFICATION_SETTINGS as $setting => $types) {
            if (in_array($type, $types, true)) {
                return $this->notificationSettings()[$setting];
            }
        }

        // A push type without a setting is always sent
        return true;
    }

    /**
     * Admin overviews: adds last_active (latest use of any login token) and lists_count (lists created)
     * as subqueries, so a page of users costs one query instead of two extra per user.
     */
    public function scopeWithActivity(Builder $query): Builder
    {
        // Cast like the token's own last_used_at, so it is sent in the same format as before
        return $query->withCasts(['last_active' => 'datetime'])->addSelect([
            'last_active' => PersonalAccessToken::selectRaw('MAX(last_used_at)')
                ->whereColumn('tokenable_id', 'users.id')
                ->where('tokenable_type', self::class),
            'lists_count' => GroceryList::withoutGlobalScopes()
                ->selectRaw('COUNT(*)')
                ->whereColumn('created_by', 'users.id'),
        ]);
    }

    // Used a login token in the last 30 days
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('tokens', fn ($q) => $q->where('last_used_at', '>=', now()->subDays(30)));
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

    public function successfulLogins()
    {
        return $this->hasMany(SuccessfulLogin::class);
    }

    public function pushTokens()
    {
        return $this->hasMany(PushToken::class, 'user_id', 'id');
    }

    /**
     * Number of lists waiting for this user to accept or decline; shown as the app icon badge.
     * Matches GroceryListController::pending: invites by user id, or by email when sent before signing up.
     */
    public function pendingInviteCount(): int
    {
        return GroceryListInvites::where('status', GroceryListInvitesStatus::PENDING)
            ->where(fn ($query) => $query->where('user_id', $this->id)->orWhere('email', $this->email))
            ->distinct()
            ->count('grocery_list_id');
    }
}
