<?php

namespace App\Http\Resources;

use App\Data\NewAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The answer to a registration, without a `data` wrapper: only what was sent in (no token: the address has to be
 * verified first), so an address that already has an account can't be told from a new one
 *
 * @property NewAccount $resource
 */
class RegistrationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token_type' => 'Bearer',
            'user' => [
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'language' => $this->resource->language,
            ],
        ];
    }
}
