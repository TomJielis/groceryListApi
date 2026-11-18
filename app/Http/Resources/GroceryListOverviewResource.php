<?php

namespace App\Http\Resources;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A list in the overview, with its counts, its creator and who joined (see ListOverviewQuery)
 *
 * @mixin GroceryList
 */
class GroceryListOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = (int) $this->getAttribute('created_by') === $request->user()->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_by' => $this->createdBy?->only(['id', 'name']),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // What is still to be bought now (the latest version of each name, for the mobile app)
            'open_items_count' => $this->open_items_count,
            // Every version of every item, and of the checked ones (the web app)
            'grocery_list_items_count' => $this->grocery_list_items_count,
            'grocery_list_items_checked_count' => $this->grocery_list_items_checked_count,
            // Only the owner manages the members (the sharing screen shows their addresses); the others get the names
            'grocery_list_invites' => $this->groceryListInvites
                ->map(fn (GroceryListInvites $invite) => new GroceryListInviteResource($invite, withEmail: $isOwner))
                ->values(),
        ];
    }
}
