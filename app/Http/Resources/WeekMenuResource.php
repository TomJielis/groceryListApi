<?php

namespace App\Http\Resources;

use App\Models\WeekMenu;
use App\Models\WeekMenuMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A week menu with who is in it (load WeekMenu::PRESENTED_WITH)
 *
 * @mixin WeekMenu
 */
class WeekMenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_by' => $this->createdBy?->only(['id', 'name']),
            'is_owner' => $this->isCreatedBy($request->user()),
            // Without the accounts deleted meanwhile
            'members' => $this->members
                ->filter(fn (WeekMenuMember $member) => $member->user)
                ->map(fn (WeekMenuMember $member) => $member->user->only(['id', 'name']))
                ->values(),
            'created_at' => $this->created_at,
        ];
    }
}
