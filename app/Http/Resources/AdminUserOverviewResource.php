<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A row of the admin users overview (a user as selected by UserOverview, with last_active, lists_count and
 * failed_logins)
 *
 * @mixin User
 */
class AdminUserOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $termsVersion = config('app.terms_version');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'email_verified' => $this->email_verified_at !== null,
            'terms_version' => $this->accepted_terms_version,
            'terms_current' => $this->accepted_terms_version === $termsVersion,
            // A raw aggregate, not cast by the model
            'last_active' => $this->last_active ? Carbon::parse($this->last_active)->toJSON() : null,
            'lists_count' => (int) $this->lists_count,
            'failed_logins' => (int) $this->failed_logins,
            'blocked' => (bool) $this->blocked,
        ];
    }
}
