<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cookbook\CookbookFavoriteRequest;
use App\Http\Requests\Cookbook\CookbookRequest;
use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryListInvitesStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cookbooks, like grocery lists: a user gets one when they register and can make more, and each can be shared by invite link (InviteLinkController). Everyone in it sees and
 * edits its recipes, but only the creator renames it, decides who is in it, and deletes it (for everyone). A cookbook
 * the user can't see answers 404, like one that doesn't exist.
 */
class CookbookController extends Controller
{
    // The cookbooks the user may see, with who is in them and how many recipes: their own first, then the ones shared
    // with them, each oldest first
    public function index(Request $request): JsonResponse
    {
        $cookbooks = Cookbook::visibleTo($request->user())
            ->with($this->relations())
            ->withCount(['recipes', 'shownRecipes'])
            ->orderByRaw('cookbooks.created_by = ? desc', [$request->user()->id])
            ->orderBy('id')
            ->get();

        $shownElsewhere = $this->shownElsewhere($cookbooks->pluck('id')->all());

        return response()->json([
            'data' => $cookbooks->map(fn (Cookbook $cookbook) => $this->present($request, $cookbook, $shownElsewhere))->values(),
        ]);
    }

    public function store(CookbookRequest $request): JsonResponse
    {
        $cookbook = Cookbook::create([
            'name' => $request->validated('name'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($request, $this->fresh($cookbook))], 201);
    }

    public function update(CookbookRequest $request, int $id): JsonResponse
    {
        $cookbook = $this->createdCookbook($request, $id);
        $cookbook->update(['name' => $request->validated('name')]);

        return response()->json(['data' => $this->present($request, $this->fresh($cookbook))]);
    }

    // The creator deletes it for everyone, with its recipes; someone it was shared with leaves it, like a shared list
    public function delete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $cookbook = $this->visibleCookbook($request, $id);

        if (!$cookbook->isCreatedBy($user)) {
            $cookbook->acceptedInvites()->where('user_id', $user->id)->delete();
            $this->forgetFavorite($cookbook, $user->id);
            $cookbook->withdrawShownBy($user->id);

            return response()->json(['message' => __('recipes.left', [], $this->language($request))]);
        }

        // Collected before the invites are gone
        $memberIds = array_diff($cookbook->memberIds(), [$user->id]);
        $cookbook->deleteWithRecipes();

        foreach ($memberIds as $memberId) {
            SendPushNotification::dispatch(
                $memberId,
                'cookbook_deleted',
                ['user' => $user->name, 'cookbook' => $cookbook->name],
                ['url' => '/recipes'],
            );
        }

        return response()->json(['message' => __('recipes.cookbook_deleted', [], $this->language($request))]);
    }

    // The creator removes someone; the cookbook and its recipes stay
    public function unshare(Request $request, int $id, int $userId): JsonResponse
    {
        $user = $request->user();
        $cookbook = $this->createdCookbook($request, $id);

        $invites = $cookbook->invites()->where('user_id', $userId);
        // Only someone who actually joined is told
        $wasMember = (clone $invites)->where('status', GroceryListInvitesStatus::ACCEPTED)->exists();
        $invites->delete();
        $this->forgetFavorite($cookbook, $userId);
        if ($userId !== (int) $cookbook->created_by) {
            $cookbook->withdrawShownBy($userId);
        }

        if ($wasMember && $userId !== $user->id) {
            SendPushNotification::dispatch(
                $userId,
                'cookbook_removed',
                ['user' => $user->name, 'cookbook' => $cookbook->name],
                ['url' => '/recipes'],
            );
        }

        return response()->json(['message' => __('recipes.member_removed', [], $this->language($request)), 'data' => $this->present($request, $this->fresh($cookbook))]);
    }

    // The cookbook new recipes go into from "all recipes", like the favorite list; null clears it
    public function favorite(CookbookFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();
        $cookbookId = $request->validated('cookbook_id');
        if ($cookbookId !== null) {
            $this->visibleCookbook($request, (int) $cookbookId);
        }

        $user->favorite_cookbook_id = $cookbookId;
        $user->save();

        return response()->json(['message' => __('recipes.favorite_updated', [], $this->language($request))]);
    }

    // Someone who is no longer in the cookbook doesn't keep it as their favorite
    private function forgetFavorite(Cookbook $cookbook, int $userId): void
    {
        User::whereKey($userId)->where('favorite_cookbook_id', $cookbook->id)->update(['favorite_cookbook_id' => null]);
    }

    private function visibleCookbook(Request $request, int $id): Cookbook
    {
        return Cookbook::visibleTo($request->user())->findOrFail($id);
    }

    // Renaming it and deciding who is in it is up to the creator only
    private function createdCookbook(Request $request, int $id): Cookbook
    {
        $cookbook = $this->visibleCookbook($request, $id);
        abort_unless($cookbook->isCreatedBy($request->user()), 403, __('recipes.only_owner', [], $this->language($request)));

        return $cookbook;
    }

    private function relations(): array
    {
        return ['createdBy:id,name', 'acceptedInvites.user:id,name'];
    }

    private function fresh(Cookbook $cookbook): Cookbook
    {
        return $cookbook->fresh()->load($this->relations())->loadCount(['recipes', 'shownRecipes']);
    }

    /**
     * Per cookbook: how many of its own recipes are shown in other cookbooks, and the names of those, for the warning
     * when its creator deletes it (the recipes go there too). In one query for all the cookbooks of the overview.
     *
     * @param list<int> $cookbookIds
     * @return array<int, array{recipes: int, cookbooks: list<string>}>
     */
    private function shownElsewhere(array $cookbookIds): array
    {
        return DB::table('cookbook_recipe')
            ->join('recipes', 'recipes.id', '=', 'cookbook_recipe.recipe_id')
            ->join('cookbooks', 'cookbooks.id', '=', 'cookbook_recipe.cookbook_id')
            ->whereIn('recipes.cookbook_id', $cookbookIds)
            ->orderBy('cookbooks.id')
            ->get(['recipes.cookbook_id as home_id', 'cookbook_recipe.recipe_id', 'cookbooks.name'])
            ->groupBy('home_id')
            ->map(fn ($rows) => [
                'recipes' => $rows->pluck('recipe_id')->unique()->count(),
                'cookbooks' => $rows->pluck('name')->unique()->values()->all(),
            ])
            ->all();
    }

    private function present(Request $request, Cookbook $cookbook, ?array $shownElsewhere = null): array
    {
        $isCreator = $cookbook->isCreatedBy($request->user());
        if ($isCreator && $shownElsewhere === null) {
            $shownElsewhere = $this->shownElsewhere([$cookbook->id]);
        }
        $accepted = $cookbook->acceptedInvites->filter(fn (CookbookInvite $invite) => $invite->user);

        return [
            'id' => $cookbook->id,
            'name' => $cookbook->name,
            'favorite' => (int) $request->user()->favorite_cookbook_id === (int) $cookbook->id,
            'created_by' => $cookbook->createdBy?->only(['id', 'name']),
            // Its own recipes and the ones of other cookbooks shown in it
            'recipes_count' => $cookbook->recipes_count + ($cookbook->shown_recipes_count ?? 0),
            // For the creator, who deletes it: its own recipes (they go with it), and how many of them other cookbooks
            // show, and which (they disappear there too)
            'own_recipes_count' => $isCreator ? (int) $cookbook->recipes_count : null,
            'shown_elsewhere' => $isCreator ? ($shownElsewhere[$cookbook->id] ?? ['recipes' => 0, 'cookbooks' => []]) : null,
            // The creator first, then who joined
            'members' => collect([$cookbook->createdBy?->only(['id', 'name'])])
                ->merge($accepted->map(fn (CookbookInvite $invite) => $invite->user->only(['id', 'name'])))
                ->filter()
                ->values(),
        ];
    }
}
