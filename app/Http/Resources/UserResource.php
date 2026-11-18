<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own account, without a `data` wrapper. Every column as the model gives it (without password
 * and remember token): the app and the website read several of them (language, accepted_terms_version,
 * favorite_list_id, is_admin ...), and a column added later reaches them without a change here.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
