<?php

namespace App\Queries\UserStats;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\User;

// The lists "My statistics" counts: the user's own and the ones they accepted an invite to
class AccessibleListsQuery
{
    /**
     * @return array<int, int>
     */
    public function idsFor(User $user): array
    {
        // Without the global scope, which would also add the shared lists (or-where) to these own ones
        $ownedListIds = GroceryList::withoutGlobalScopes()
            ->where('created_by', $user->id)
            ->pluck('id');

        $sharedListIds = GroceryListInvites::where('user_id', $user->id)
            ->where('status', GroceryListInvitesStatus::ACCEPTED)
            ->pluck('grocery_list_id');

        return $ownedListIds->merge($sharedListIds)->unique()->toArray();
    }
}
