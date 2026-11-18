<?php

namespace App\Http\Resources;

use App\Models\InvalidLoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One failed login on the admin overview (load user:id,name,email)
 *
 * @mixin InvalidLoginAttempt
 */
class FailedLoginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Rows from before the username column only have the account
            'email' => $this->username ?? $this->user?->email,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'ip_address' => $this->ip_address,
            'attempted_at' => $this->attempted_at,
            // This attempt locked the account for 15 minutes
            'caused_lockout' => $this->blocked_till !== null,
        ];
    }
}
