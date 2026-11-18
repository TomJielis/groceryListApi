<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\User;

// Taking people off a list, leaving it and deleting it, with the pushes that go with it
class GroceryListService
{
    // Removes someone from a list (the caller checked the list is one the user may see)
    public function unshare(int $listId, int $userId, User $by): void
    {
        $invites = GroceryListInvites::where('grocery_list_id', $listId)
            ->where('user_id', $userId);
        // Only someone who actually joined is told
        $wasMember = (clone $invites)->where('status', GroceryListInvitesStatus::ACCEPTED)->exists();
        $invites->delete();

        $groceryList = GroceryList::withoutGlobalScopes()->find($listId);
        if ($wasMember && $groceryList && $userId !== $by->id) {
            SendPushNotification::dispatch(
                $userId,
                'list_removed',
                ['user' => $by->name, 'list' => $groceryList->name],
                ['url' => '/'],
            );
        }
    }

    // Someone who joined a list leaves it
    public function leave(GroceryList $list, User $user): void
    {
        $groceryListInvite = GroceryListInvites::where('grocery_list_id', $list->id)
            ->where('user_id', $user->id)
            ->where('status', '=', GroceryListInvitesStatus::ACCEPTED)
            ->first();

        $groceryListInvite->delete();
    }

    // The owner deletes a list for everyone; the others on it are told
    public function delete(GroceryList $list, User $by): void
    {
        // Collected before the invites are gone
        $memberIds = array_diff($list->memberIds(), [$by->id]);

        $list->groceryListInvites()->delete();
        $list->groceryListItems()->delete();
        $list->delete();

        foreach ($memberIds as $memberId) {
            SendPushNotification::dispatch(
                $memberId,
                'list_deleted',
                ['user' => $by->name, 'list' => $list->name],
                ['url' => '/'],
            );
        }
    }
}
