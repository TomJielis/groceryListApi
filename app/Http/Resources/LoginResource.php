<?php

namespace App\Http\Resources;

use App\Data\LoginResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A successful login: the token for this device and the account, without a `data` wrapper
 *
 * @property LoginResult $resource
 */
class LoginResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->token,
            'token_type' => 'Bearer',
            'user' => new UserResource($this->resource->user),
        ];
    }
}
