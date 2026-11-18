<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cookbook\CookbookFavoriteRequest;
use App\Http\Requests\Cookbook\CookbookRequest;
use App\Http\Resources\CookbookResource;
use App\Models\Cookbook;
use App\Queries\Cookbook\CookbookShownElsewhereQuery;
use App\Services\CookbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cookbooks, like grocery lists: a user gets one when they register and can make more, and each can be shared by invite link (InviteLinkController). Everyone in it sees and
 * edits its recipes, but only the creator renames it, decides who is in it, and deletes it (for everyone). A cookbook
 * the user can't see answers 404, like one that doesn't exist.
 */
class CookbookController extends Controller
{
    public function __construct(private CookbookService $cookbooks)
    {
    }

    // The cookbooks the user may see, with who is in them and how many recipes: their own first, then the ones shared
    // with them, each oldest first
    public function index(Request $request, CookbookShownElsewhereQuery $shownElsewhere): AnonymousResourceCollection
    {
        $cookbooks = Cookbook::visibleTo($request->user())
            ->with(Cookbook::PRESENTED_WITH)
            ->withCount(Cookbook::PRESENTED_COUNTS)
            ->orderByRaw('cookbooks.created_by = ? desc', [$request->user()->id])
            ->orderBy('id')
            ->get();

        // In one query for all of them, not one per cookbook
        $shown = $shownElsewhere->forCookbooks($cookbooks->pluck('id')->all());

        return CookbookResource::collection($cookbooks->map(fn (Cookbook $cookbook) => new CookbookResource($cookbook, $shown)));
    }

    public function store(CookbookRequest $request): JsonResponse
    {
        $cookbook = Cookbook::create(['name' => $request->cookbookName(), 'created_by' => $request->user()->id]);

        return (new CookbookResource($this->fresh($cookbook)))->response()->setStatusCode(201);
    }

    public function update(CookbookRequest $request, int $id): CookbookResource
    {
        $cookbook = $this->createdCookbook($request, $id);
        $cookbook->update(['name' => $request->cookbookName()]);

        return new CookbookResource($this->fresh($cookbook));
    }

    // The creator deletes it for everyone, with its recipes; someone it was shared with leaves it, like a shared list
    public function delete(Request $request, int $id): JsonResponse
    {
        $cookbook = $this->visibleCookbook($request, $id);

        if (!$cookbook->isCreatedBy($request->user())) {
            $this->cookbooks->leave($cookbook, $request->user());

            return response()->json(['message' => __('recipes.left', [], $this->language($request))]);
        }

        $this->cookbooks->deleteForEveryone($cookbook, $request->user());

        return response()->json(['message' => __('recipes.cookbook_deleted', [], $this->language($request))]);
    }

    // The creator removes someone; the cookbook and its recipes stay
    public function unshare(Request $request, int $id, int $userId): JsonResponse
    {
        $cookbook = $this->createdCookbook($request, $id);
        $this->cookbooks->removeMember($cookbook, $userId, $request->user());

        return response()->json([
            'message' => __('recipes.member_removed', [], $this->language($request)),
            'data' => new CookbookResource($this->fresh($cookbook)),
        ]);
    }

    // The cookbook new recipes go into from "all recipes", like the favorite list; null clears it
    public function favorite(CookbookFavoriteRequest $request): JsonResponse
    {
        $cookbookId = $request->cookbookId();
        if ($cookbookId !== null) {
            $this->visibleCookbook($request, $cookbookId);
        }

        $request->user()->favorite_cookbook_id = $cookbookId;
        $request->user()->save();

        return response()->json(['message' => __('recipes.favorite_updated', [], $this->language($request))]);
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

    private function fresh(Cookbook $cookbook): Cookbook
    {
        return $cookbook->fresh()->load(Cookbook::PRESENTED_WITH)->loadCount(Cookbook::PRESENTED_COUNTS);
    }
}
