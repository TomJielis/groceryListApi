<?php

namespace App\Services;

use App\Models\InviteLink;
use App\Models\Recipe;
use App\Models\User;
use App\Models\WeekMenu;
use App\Models\WeekMenuEntry;
use Illuminate\Support\Facades\DB;

/**
 * Changing what's in a week menu where more people can do it at the same time: planning and moving dishes (each gets
 * a place after the ones already on that day), claiming dishes for "put on my list", and taking someone out
 */
class WeekMenuService
{
    // Plans the recipe on the day, after the dishes already there; for its own number of servings when none is given
    public function plan(WeekMenu $menu, Recipe $recipe, string $date, ?int $servings, User $by): WeekMenuEntry
    {
        return DB::transaction(function () use ($menu, $recipe, $date, $servings, $by) {
            $this->lock($menu->id);

            return WeekMenuEntry::create([
                'week_menu_id' => $menu->id,
                'date' => $date,
                'recipe_id' => $recipe->id,
                'servings' => $servings ?? max(1, (int) $recipe->servings),
                'position' => $this->nextPosition($menu->id, $date),
                'added_by' => $by->id,
            ]);
        });
    }

    /**
     * Changes a planned dish; moved to another day, it goes after the dishes already there
     *
     * @param array<string, mixed> $changes
     */
    public function update(WeekMenuEntry $entry, array $changes): WeekMenuEntry
    {
        DB::transaction(function () use ($entry, $changes) {
            if (isset($changes['date']) && $changes['date'] !== $entry->date->toDateString()) {
                $this->lock($entry->week_menu_id);
                $changes['position'] = $this->nextPosition($entry->week_menu_id, $changes['date']);
            }
            $entry->update($changes);
        });

        return $entry;
    }

    /**
     * "Put on my list": claims the dishes nobody put on a list yet (two people tapping at once don't both add them),
     * and answers which ones the user got
     *
     * @param list<int> $entryIds
     * @return list<int>
     */
    public function claimForList(WeekMenu $menu, array $entryIds, ?int $listId, User $by): array
    {
        return DB::transaction(function () use ($menu, $entryIds, $listId, $by) {
            $ids = $menu->entries()->whereIn('id', $entryIds)->whereNull('added_to_list_at')
                ->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids) {
                WeekMenuEntry::whereIn('id', $ids)->update([
                    'added_to_list_at' => now(),
                    'added_to_list_by' => $by->id,
                    'added_to_list_id' => $listId,
                    // For how many servings they went on it: "servings changed" when that's changed since
                    'added_to_list_servings' => DB::raw('servings'),
                ]);
            }

            return $ids;
        });
    }

    /**
     * Gives back the user's own claim, when putting the dishes on the list failed after all; answers how many
     *
     * @param list<int> $entryIds
     */
    public function releaseClaim(WeekMenu $menu, array $entryIds, User $by): int
    {
        return $menu->entries()->whereIn('id', $entryIds)->where('added_to_list_by', $by->id)->update(WeekMenuEntry::NOT_ADDED);
    }

    /**
     * Someone out of a menu. Their planned dishes stay: they could plan them, and the others can save their own copy.
     * Removed by the creator: the menu's invite links stop working too, so a link they have seen doesn't let them
     * back in; the creator shares a new one with whoever should join.
     */
    public function takeOut(WeekMenu $menu, int $userId, bool $removedByCreator): void
    {
        DB::transaction(function () use ($menu, $userId, $removedByCreator) {
            $menu->members()->where('user_id', $userId)->delete();
            if ($removedByCreator) {
                InviteLink::where('week_menu_id', $menu->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
        });
    }

    // The menu locked: two people planning on the same day at once get a place each
    private function lock(int $menuId): void
    {
        WeekMenu::whereKey($menuId)->lockForUpdate()->first();
    }

    private function nextPosition(int $menuId, string $date): int
    {
        return (int) WeekMenuEntry::where('week_menu_id', $menuId)->where('date', $date)->max('position') + 1;
    }
}
