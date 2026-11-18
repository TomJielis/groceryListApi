<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin's page about one user: their account, latest logins and their numbers (UserActivity), without a data
 * wrapper
 *
 * @mixin User
 */
class AdminUserDetailResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param array<string, mixed> $activity
     */
    public function __construct(User $user, private array $activity)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'created_at' => $this->created_at,
                'email_verified_at' => $this->email_verified_at,
                'terms_version' => $this->accepted_terms_version,
                'last_active' => $this->last_active_at,
                'blocked' => $this->blocked,
            ],
            // Newest first
            'recent_logins' => $this->successfulLogins()
                ->orderByDesc('logged_in_at')
                ->limit(10)
                ->get(['ip_address', 'client', 'app_version', 'logged_in_at']),
            ...$this->activity,
        ];
    }
}
