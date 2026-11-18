<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whether the user has the week planner button in the app
 *
 * @mixin User
 */
class WeekPlannerSettingResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    public function toArray(Request $request): array
    {
        return [
            'week_planner' => $this->week_planner,
        ];
    }
}
