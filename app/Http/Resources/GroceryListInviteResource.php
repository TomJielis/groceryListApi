<?php

namespace App\Http\Resources;

use App\Models\GroceryListInvites;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Someone who joined a list, in the list overview. Their address only for the list's owner, who manages the members
 * (decided per list: the same person can be in the user's own list and in someone else's).
 *
 * @mixin GroceryListInvites
 */
class GroceryListInviteResource extends JsonResource
{
    public function __construct(GroceryListInvites $invite, private bool $withEmail)
    {
        parent::__construct($invite);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grocery_list_id' => $this->grocery_list_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // An account deleted meanwhile is null
            'user' => $this->user?->only($this->withEmail ? ['id', 'name', 'email'] : ['id', 'name']),
        ];
    }
}
