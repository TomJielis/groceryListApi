<?php

namespace App\Queries\GroceryList;

use App\Models\GroceryList;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

// The lists the user may see (GroceryList's global scope), for the overview in the app and on the website
class ListOverviewQuery
{
    /**
     * @param array{0: mixed, 1: mixed}|null $page [offset, limit], or null for every list
     * @return Collection<int, GroceryList>
     */
    public function forUser(User $user, ?array $page): Collection
    {
        // Items are versioned per name (a new row per change); the current item is the latest row of each name.
        // The two counts below include every version and stay as they are for the web app;
        // open_items_count is what is still to be bought right now (used by the mobile app).
        $latestIds = GroceryListItem::selectRaw('MAX(id)')
            ->whereColumn('list_id', 'grocery_lists.id')
            ->groupBy('name');
        $openItemsCount = GroceryListItem::selectRaw('COUNT(*)')
            ->whereColumn('list_id', 'grocery_lists.id')
            ->where('checked', false)
            ->whereIn('id', $latestIds);

        $query = GroceryList::select('*')
            ->selectSub($openItemsCount, 'open_items_count')
            ->withCount('groceryListItems')
            ->withCount('groceryListItemsChecked')
            ->with([
                // Everyone who joined (the owner is createdBy)
                'groceryListInvites' => fn ($query) => $query->where('status', GroceryListInvitesStatus::ACCEPTED)->with('user:id,name,email'),
                'createdBy:id,name',
            ])
            // The user's favorite on top, then the most still to buy; equal lists stay oldest first
            ->orderByRaw('CASE WHEN grocery_lists.id = ? THEN 0 ELSE 1 END', [$user->favorite_list_id])
            ->orderByDesc('open_items_count')
            ->orderBy('grocery_lists.id');

        if ($page !== null) {
            [$offset, $limit] = $page;
            $query->limit($limit)->offset($offset);
        }

        // Who sees the members' addresses is up to the resource, per list (GroceryListOverviewResource)
        return $query->get();
    }
}
