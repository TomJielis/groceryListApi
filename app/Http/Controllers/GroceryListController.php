<?php

namespace App\Http\Controllers;

use App\Http\Requests\GroceryList\GroceryListFavoriteRequest;
use App\Http\Requests\GroceryList\GroceryListIndexRequest;
use App\Http\Requests\GroceryList\GroceryListNameRequest;
use App\Http\Requests\GroceryList\GroceryListUnshareRequest;
use App\Http\Resources\GroceryListOverviewResource;
use App\Http\Resources\GroceryListResource;
use App\Models\GroceryList;
use App\Queries\GroceryList\ListOverviewQuery;
use App\Services\GroceryListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The user's grocery lists: their own and the ones they joined by invite link (GroceryList's global scope finds
 * exactly those, so another list answers 404). Members rename a list too; only its owner deletes it for everyone.
 */
class GroceryListController extends Controller
{
    public function __construct(private GroceryListService $lists)
    {
    }

    public function index(GroceryListIndexRequest $request, ListOverviewQuery $overview): AnonymousResourceCollection
    {
        return GroceryListOverviewResource::collection($overview->forUser($request->user(), $request->page()));
    }

    public function store(GroceryListNameRequest $request): GroceryListResource
    {
        $list = GroceryList::create(['name' => $request->listName(), 'created_by' => $request->user()->id]);

        return new GroceryListResource($list);
    }

    public function update(GroceryListNameRequest $request, GroceryList $groceryList): GroceryListResource
    {
        $groceryList->name = $request->listName() ?? $groceryList->name;
        $groceryList->save();

        return new GroceryListResource($groceryList);
    }

    public function unshare(GroceryListUnshareRequest $request): JsonResponse
    {
        if (!$request->isComplete()) {
            return response()->json(['message' => __('lists.invalid_request', [], $this->language($request))], 400);
        }

        // Only someone with access to the list (owner or member) can remove people from it
        if (!GroceryList::find($request->listId())) {
            return response()->json(['message' => __('lists.not_found', [], $this->language($request))], 404);
        }

        $this->lists->unshare($request->listId(), $request->userId(), $request->user());

        return response()->json(['message' => __('lists.access_revoked', [], $this->language($request))]);
    }

    public function favorite(GroceryListFavoriteRequest $request): JsonResponse
    {
        $listId = $request->listId();
        // Only a list the user can see can be their favorite (null clears it)
        if ($listId && !GroceryList::find($listId)) {
            return response()->json(['message' => __('lists.not_found', [], $this->language($request))], 404);
        }

        $user = $request->user();
        $user->favorite_list_id = $listId;
        $user->save();

        return response()->json(['message' => __('lists.marked_favorite', [], $this->language($request))]);
    }

    // The owner deletes it for everyone; someone who joined leaves it
    public function delete(Request $request, GroceryList $groceryList): JsonResponse
    {
        if ($groceryList->created_by !== $request->user()->id) {
            $this->lists->leave($groceryList, $request->user());
        } else {
            $this->lists->delete($groceryList, $request->user());
        }

        return response()->json(['message' => __('lists.deleted', [], $this->language($request))]);
    }
}
