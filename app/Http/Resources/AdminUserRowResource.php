<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user in the admin's list of users (load User::withActivity)
 *
 * @mixin User
 */
class AdminUserRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'email_verified' => $this->email_verified_at !== null,
            'terms_version' => $this->accepted_terms_version,
            'last_active' => $this->last_active,
            'lists_count' => (int) $this->lists_count,
            'blocked' => $this->blocked,
        ];
    }
}
