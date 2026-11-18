<?php

namespace App\Http\Controllers;

use App\Http\Requests\GroceryListItem\GroceryListItemAmountRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemCheckedRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemDeleteManyRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemIndexRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemMoveRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemStoreRequest;
use App\Http\Requests\GroceryListItem\GroceryListItemUpdateRequest;
use App\Http\Resources\GroceryListItemResource;
use App\Models\GroceryListItem;
use App\Queries\GroceryList\CurrentItemsQuery;
use App\Services\GroceryListItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The items of the lists. Items are versioned per name: the latest row of a name in a list is the current item. Only
 * items on lists the user may see are touched (the requests answer 403 for another list, see ListFormRequest).
 */
class GroceryListItemController extends Controller
{
    public function __construct(private GroceryListItemService $items)
    {
    }

    public function index(GroceryListItemIndexRequest $request, CurrentItemsQuery $currentItems): AnonymousResourceCollection
    {
        return GroceryListItemResource::collection($currentItems->get($request->listId(), $request->page()));
    }

    public function store(GroceryListItemStoreRequest $request): GroceryListItemResource
    {
        $listItem = $this->items->add($request->listId(), $request->itemName(), $request->quantity(), $request->user());

        return new GroceryListItemResource($listItem);
    }

    // Checks off the current item of this name (the app may still have an older version of it)
    public function checked(GroceryListItemCheckedRequest $request, GroceryListItem $listItem): GroceryListItemResource
    {
        return new GroceryListItemResource($this->items->check($listItem, $request->checkedValue(), $request->user()));
    }

    public function update(GroceryListItemUpdateRequest $request, GroceryListItem $listItem): GroceryListItemResource
    {
        return new GroceryListItemResource($this->items->update($listItem, $request->changes()));
    }

    public function increase(GroceryListItemAmountRequest $request, GroceryListItem $listItem): GroceryListItemResource
    {
        $listItem->increment('quantity', $request->amount());

        return new GroceryListItemResource($listItem);
    }

    // Answers the item, also when nothing was left of it and it was deleted
    public function decrease(GroceryListItemAmountRequest $request, GroceryListItem $listItem): GroceryListItemResource
    {
        return new GroceryListItemResource($this->items->decrease($listItem, $request->amount()));
    }

    public function delete(GroceryListItemRequest $request, GroceryListItem $listItem): JsonResponse
    {
        $listItem->delete();

        return response()->json(['message' => __('lists.item_deleted', [], $this->language($request))]);
    }

    // Moves several products to another list of the user's (GroceryListItemService::move)
    public function move(GroceryListItemMoveRequest $request): JsonResponse
    {
        return response()->json([
            'moved' => $this->items->move($request->itemIds(), $request->targetListId(), $request->user()),
        ]);
    }

    // Deletes several products at once, like one by one; only on lists the user may see
    public function deleteMany(GroceryListItemDeleteManyRequest $request): JsonResponse
    {
        return response()->json(['deleted' => $this->items->deleteMany($request->itemIds())]);
    }
}
