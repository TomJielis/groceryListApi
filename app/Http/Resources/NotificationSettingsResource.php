<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every push setting of the user with its value (User::notificationSettings), e.g. {"items_added": false, ...}
 *
 * @mixin User
 */
class NotificationSettingsResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    public function toArray(Request $request): array
    {
        return $this->notificationSettings();
    }
}
