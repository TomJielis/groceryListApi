<?php

namespace App\Queries\UserStats;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Carbon\Carbon;

// Everyone on the given lists (owners and who accepted an invite) with what they added and checked off in a month
class UserBreakdownQuery
{
    /**
     * @return list<array{user_id: int, user_name: string, checked: int, added: int}>
     */
    public function forMonth(array $listIds, Carbon $month): array
    {
        if (empty($listIds)) {
            return [];
        }

        $ownerUserIds = GroceryList::withoutGlobalScopes()
            ->whereIn('id', $listIds)
            ->pluck('created_by');

        $inviteeUserIds = GroceryListInvites::whereIn('grocery_list_id', $listIds)
            ->where('status', GroceryListInvitesStatus::ACCEPTED)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $allUserIds = $ownerUserIds->merge($inviteeUserIds)->unique()->values();

        return User::whereIn('id', $allUserIds)
            ->select('id', 'name')
            ->get()
            ->map(function (User $member) use ($listIds, $month) {
                $checked = (int) GroceryListItem::whereIn('list_id', $listIds)
                    ->where('checked', true)
                    ->where('updated_by', $member->id)
                    ->whereYear('updated_at', $month->year)
                    ->whereMonth('updated_at', $month->month)
                    ->sum('quantity');

                $added = (int) GroceryListItem::whereIn('list_id', $listIds)
                    ->where('created_by', $member->id)
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('quantity');

                return [
                    'user_id'   => $member->id,
                    'user_name' => $member->name,
                    'checked'   => $checked,
                    'added'     => $added,
                ];
            })
            ->values()
            ->toArray();
    }
}
