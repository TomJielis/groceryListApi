<?php

namespace App\Http\Controllers;

use App\Http\Requests\WeekMenu\WeekMenuAddedToListRequest;
use App\Http\Requests\WeekMenu\WeekMenuEntriesRequest;
use App\Http\Requests\WeekMenu\WeekMenuEntryStoreRequest;
use App\Http\Requests\WeekMenu\WeekMenuEntryUpdateRequest;
use App\Http\Requests\WeekMenu\WeekMenuStoreRequest;
use App\Http\Requests\WeekMenu\WeekMenuUpdateRequest;
use App\Http\Resources\WeekMenuEntryResource;
use App\Http\Resources\WeekMenuResource;
use App\Models\GroceryList;
use App\Models\Recipe;
use App\Models\WeekMenu;
use App\Models\WeekMenuEntry;
use App\Services\WeekMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Week menus: recipes planned per date, made by hand and shared by invite link (InviteLinkController). Everyone in a
 * menu plans in it; only its creator renames it, removes people and deletes it. A menu the user isn't in answers 404,
 * like one that doesn't exist. Putting a week on a list goes through the week planner's add (the app combines the
 * ingredients), after which the app marks the entries as added (addedToList).
 */
class WeekMenuController extends Controller
{
    public function __construct(private WeekMenuService $menus)
    {
    }

    // The menus the user is in: their own first, then the ones they joined, each oldest first
    public function index(Request $request): AnonymousResourceCollection
    {
        $menus = WeekMenu::visibleTo($request->user())
            ->with(WeekMenu::PRESENTED_WITH)
            ->orderByRaw('week_menus.created_by = ? desc', [$request->user()->id])
            ->orderBy('id')
            ->get();

        return WeekMenuResource::collection($menus);
    }

    public function store(WeekMenuStoreRequest $request): WeekMenuResource
    {
        $menu = WeekMenu::create(['name' => $request->menuName(), 'created_by' => $request->user()->id]);

        return new WeekMenuResource($menu->load(WeekMenu::PRESENTED_WITH));
    }

    public function update(WeekMenuUpdateRequest $request, int $id): WeekMenuResource
    {
        $menu = $this->createdMenu($request, $id);
        $menu->update(['name' => $request->menuName()]);

        return new WeekMenuResource($menu->fresh()->load(WeekMenu::PRESENTED_WITH));
    }

    // The creator deletes it for everyone; someone who joined leaves it
    public function delete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $menu = $this->visibleMenu($request, $id);

        if (!$menu->isCreatedBy($user)) {
            $this->menus->takeOut($menu, $user->id, false);

            return response()->json(['message' => __('week-menus.left', [], $this->language($request))]);
        }

        // The entries, members and invite links go with it through their foreign keys
        $menu->delete();

        return response()->json(['message' => __('week-menus.deleted', [], $this->language($request))]);
    }

    // The creator removes someone, or someone takes themselves out
    public function removeMember(Request $request, int $id, int $userId): WeekMenuResource
    {
        $user = $request->user();
        $menu = $this->visibleMenu($request, $id);
        abort_unless($menu->isCreatedBy($user) || $userId === $user->id, 403, __('week-menus.only_owner', [], $this->language($request)));
        // The creator stays in their own menu (they delete it instead)
        abort_if((int) $menu->created_by === $userId, 422, __('week-menus.owner_cannot_leave', [], $this->language($request)));
        // Someone who isn't in it: nothing to do (and the menu's links stay)
        abort_unless($menu->members()->where('user_id', $userId)->exists(), 404);

        $this->menus->takeOut($menu, $userId, $menu->isCreatedBy($user));

        return new WeekMenuResource($menu->fresh()->load(WeekMenu::PRESENTED_WITH));
    }

    // The planned recipes from one date to another (both included)
    public function entries(WeekMenuEntriesRequest $request, int $id): AnonymousResourceCollection
    {
        $entries = $this->visibleMenu($request, $id)->entries()
            ->whereBetween('date', [$request->from()->toDateString(), $request->to()->toDateString()])
            ->with(WeekMenuEntry::PRESENTED_WITH)
            ->orderBy('date')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return WeekMenuEntryResource::collection($entries);
    }

    // Plans a recipe from the user's recipe book on a day; after the ones already there
    public function storeEntry(WeekMenuEntryStoreRequest $request, int $id): WeekMenuEntryResource|JsonResponse
    {
        $menu = $this->visibleMenu($request, $id);
        // Only from their own recipe book: planning it is what lets the others in the menu read it
        $recipe = Recipe::visibleTo($request->user())->find($request->validated('recipe_id'));
        if (!$recipe) {
            return response()->json(['message' => __('week-menus.no_recipe_access', [], $this->language($request))], 403);
        }

        $entry = $this->menus->plan($menu, $recipe, $request->validated('date'), $request->servings(), $request->user());

        return new WeekMenuEntryResource($entry->load(WeekMenuEntry::PRESENTED_WITH));
    }

    // Moves a planned recipe to another day, plans it for another number of servings, or puts it on the list again
    public function updateEntry(WeekMenuEntryUpdateRequest $request, int $id, int $entryId): WeekMenuEntryResource
    {
        $entry = $this->menus->update($this->menuEntry($request, $id, $entryId), $request->changes());

        return new WeekMenuEntryResource($entry->fresh()->load(WeekMenuEntry::PRESENTED_WITH));
    }

    public function deleteEntry(Request $request, int $id, int $entryId): JsonResponse
    {
        $this->menuEntry($request, $id, $entryId)->delete();

        return response()->json(['message' => __('week-menus.entry_deleted', [], $this->language($request))]);
    }

    /**
     * "Put on my list": the app first claims the dishes here, then puts the ingredients of the ones it got on the list.
     * Only dishes nobody put on a list yet are claimed; marked_ids says which. undo gives back the user's own claim,
     * when putting them on the list failed after all.
     */
    public function addedToList(WeekMenuAddedToListRequest $request, int $id): JsonResponse
    {
        $menu = $this->visibleMenu($request, $id);

        if ($request->undo()) {
            $count = $this->menus->releaseClaim($menu, $request->entryIds(), $request->user());

            return response()->json(['data' => ['updated' => $count, 'marked_ids' => []]]);
        }

        // Only a list the user can see (the global scope): another one isn't named
        $listId = $request->listId() && GroceryList::find($request->listId()) ? $request->listId() : null;
        $marked = $this->menus->claimForList($menu, $request->entryIds(), $listId, $request->user());

        return response()->json(['data' => ['updated' => count($marked), 'marked_ids' => $marked]]);
    }

    private function visibleMenu(Request $request, int $id): WeekMenu
    {
        return WeekMenu::visibleTo($request->user())->findOrFail($id);
    }

    private function createdMenu(Request $request, int $id): WeekMenu
    {
        $menu = $this->visibleMenu($request, $id);
        abort_unless($menu->isCreatedBy($request->user()), 403, __('week-menus.only_owner', [], $this->language($request)));

        return $menu;
    }

    private function menuEntry(Request $request, int $id, int $entryId): WeekMenuEntry
    {
        return $this->visibleMenu($request, $id)->entries()->findOrFail($entryId);
    }
}
