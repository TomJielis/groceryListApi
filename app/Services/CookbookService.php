<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\GroceryListInvitesStatus;
use App\Models\User;

/**
 * Who is in a cookbook: someone leaving it, its creator removing someone or deleting it for everyone. Whoever is no
 * longer in it loses it as their favorite and takes back what they showed (Cookbook::withdrawShownBy).
 */
class CookbookService
{
    // Someone it was shared with leaves it, like a shared list
    public function leave(Cookbook $cookbook, User $user): void
    {
        $cookbook->acceptedInvites()->where('user_id', $user->id)->delete();
        $this->forgetFavorite($cookbook, $user->id);
        $cookbook->withdrawShownBy($user->id);
    }

    // The creator deletes it for everyone, with its recipes; the others hear about it
    public function deleteForEveryone(Cookbook $cookbook, User $by): void
    {
        // Collected before the invites are gone
        $memberIds = array_diff($cookbook->memberIds(), [$by->id]);
        $cookbook->deleteWithRecipes();

        foreach ($memberIds as $memberId) {
            $this->notify($memberId, 'cookbook_deleted', $by, $cookbook);
        }
    }

    // The creator removes someone; the cookbook and its recipes stay
    public function removeMember(Cookbook $cookbook, int $userId, User $by): void
    {
        $invites = $cookbook->invites()->where('user_id', $userId);
        // Only someone who actually joined is told
        $wasMember = (clone $invites)->where('status', GroceryListInvitesStatus::ACCEPTED)->exists();
        $invites->delete();
        $this->forgetFavorite($cookbook, $userId);
        if ($userId !== (int) $cookbook->created_by) {
            $cookbook->withdrawShownBy($userId);
        }

        if ($wasMember && $userId !== $by->id) {
            $this->notify($userId, 'cookbook_removed', $by, $cookbook);
        }
    }

    // Someone who is no longer in the cookbook doesn't keep it as their favorite
    private function forgetFavorite(Cookbook $cookbook, int $userId): void
    {
        User::whereKey($userId)->where('favorite_cookbook_id', $cookbook->id)->update(['favorite_cookbook_id' => null]);
    }

    private function notify(int $userId, string $type, User $by, Cookbook $cookbook): void
    {
        SendPushNotification::dispatch($userId, $type, ['user' => $by->name, 'cookbook' => $cookbook->name], ['url' => '/recipes']);
    }
}
