<?php

namespace App\Services;

use App\Models\InviteLink;
use App\Models\Recipe;
use App\Models\User;
use App\Models\WeekMenu;
use App\Models\WeekMenuEntry;
use App\Models\WeekMenuMember;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Joining a week menu by invite link. Joining shouldn't leave the user with two menus: someone with a menu of their own
 * with dishes in it is asked whether to bring them along (ownMenuToBring), and their own empty menus are tidied up.
 */
class WeekMenuJoinService
{
    public const JOINED = 'joined';
    // In it already (a double tap, or a retry)
    public const MEMBER = 'member';
    // Its creator deleted it (or brought it into another menu) meanwhile
    public const GONE = 'gone';

    /**
     * The user's own week menu that would make a second one next to the menu they join: one only they are in, with
     * dishes in it. Null when there's none (then joining just joins).
     *
     * @return array{id: int, name: string, entries_count: int}|null
     */
    public function ownMenuToBring(User $user, int $joiningId): ?array
    {
        $bringable = fn ($query) => $query->whereIn('recipe_id', Recipe::visibleTo($user)->select('recipes.id'));
        $menu = WeekMenu::where('created_by', $user->id)
            ->whereKeyNot($joiningId)
            ->whereDoesntHave('members')
            ->whereHas('entries', $bringable)
            ->withCount(['entries' => $bringable])
            ->orderBy('id')
            ->first();

        return $menu ? ['id' => $menu->id, 'name' => $menu->name, 'entries_count' => (int) $menu->entries_count] : null;
    }

    /**
     * Joins the menu of the link: one of JOINED, MEMBER or GONE. The menu whose dishes come along: the one the screen
     * named ($bringMenuId), or with only $bringDishes the same one ownMenuToBring() named. Never another menu: one the
     * user isn't the only one in, or that isn't theirs, is left alone by tidyOwnMenus.
     *
     * @throws LockTimeoutException when another join of this user takes too long
     */
    public function join(InviteLink $link, User $user, ?int $bringMenuId, bool $bringDishes): string
    {
        $menuId = (int) $link->week_menu_id;
        $bringId = $bringMenuId ?: ($bringDishes ? ($this->ownMenuToBring($user, $menuId)['id'] ?? null) : null);

        // One join at a time per user: a double tap (or a retry) then finds the membership instead of failing
        return Cache::lock("week-menu-join:{$user->id}", 30)->block(10, fn () => DB::transaction(function () use ($menuId, $user, $bringId) {
            // Locked, so it can't be deleted while the user joins it
            if (!WeekMenu::whereKey($menuId)->lockForUpdate()->exists()) {
                return self::GONE;
            }
            if (WeekMenuMember::where('week_menu_id', $menuId)->where('user_id', $user->id)->exists()) {
                return self::MEMBER;
            }
            WeekMenuMember::createOrFirst(['week_menu_id' => $menuId, 'user_id' => $user->id]);
            $this->tidyOwnMenus($user, $menuId, $bringId);

            return self::JOINED;
        }));
    }

    /**
     * Their own empty menus only they are in go, unless a working invite link of it is still out (someone may be
     * joining it). The menu they chose to bring along ($bringId: own, only they are in it) moves its dishes to the
     * joined menu and goes. Menus someone else is in stay. Each is locked and checked again just before it goes, so one
     * someone joins at that moment is kept.
     */
    private function tidyOwnMenus(User $user, int $joiningId, ?int $bringId): void
    {
        $own = WeekMenu::where('created_by', $user->id)->whereKeyNot($joiningId)->lockForUpdate()->get();
        foreach ($own as $menu) {
            if ($menu->members()->exists()) {
                continue;
            }
            $hasDishes = $menu->entries()->exists();
            if ((int) $menu->id === $bringId && $hasDishes) {
                // The user's dishes, now planned in the joined menu (the others there can read their recipes). Only
                // recipes they can see themselves: a dish someone planned before leaving their menu doesn't pass that
                // person's private recipe on to people it was never shared with; it goes with the menu.
                $offset = (int) WeekMenuEntry::where('week_menu_id', $joiningId)->max('position') + 1;
                $menu->entries()
                    ->whereIn('recipe_id', Recipe::visibleTo($user)->select('recipes.id'))
                    ->update(['week_menu_id' => $joiningId, 'position' => DB::raw('position + ' . $offset)]);
                $menu->delete();
            } elseif (!$hasDishes && !$this->hasWorkingLink($menu)) {
                $menu->delete();
            }
        }
    }

    private function hasWorkingLink(WeekMenu $menu): bool
    {
        return InviteLink::where('week_menu_id', $menu->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
    }
}
