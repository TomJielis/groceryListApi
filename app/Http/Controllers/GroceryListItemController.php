<?php

namespace App\Http\Controllers;

use App\Jobs\SendListActivityPush;
use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroceryListItemController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $offset = $request->get('from');
        $limit = $request->get('till');
        $listId = $request->get('listId');

        // The lists the user may see (own and accepted shares; GroceryList's global scope), narrowed down first so
        // the grouping below only touches their items, not those of every user
        $listIds = GroceryList::query()->pluck('id');
        if (isset($listId)) {
            $listIds = $listIds->filter(fn ($id) => (int) $id === (int) $listId)->values();
        }

        // The latest item per name in each list
        $latestIds = GroceryListItem::selectRaw('MAX(id) as id')
            ->whereIn('list_id', $listIds)
            ->groupBy('list_id', 'name');

        $listItems = GroceryListItem::select('grocery_list_items.*')
            ->joinSub($latestIds, 'latest', 'grocery_list_items.id', '=', 'latest.id')
            ->orderBy('grocery_list_items.id');

        if (isset($offset) && isset($limit)) {
            $listItems->limit($limit)
                ->offset($offset);
        }

        return response()->json([
            'data' => $listItems->get(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $this->ensureListAccess($data['list_id'] ?? null);

        $existingListItem = GroceryListItem::where('name', $data['name'])
            ->where('list_id', $data['list_id'] ?? null)
            ->orderByDesc('id')
            ->first();

        $listItem = GroceryListItem::create([
            'name' => ucfirst($data['name']),
            'quantity' => $data['quantity'] ?? 1,
            'list_id' => $data['list_id'] ?? null,
            'unit_price' => $existingListItem['unit_price'] ?? null,
        ]);

        if ($listItem->list_id) {
            SendListActivityPush::record((int) $listItem->list_id, auth()->id(), 'items_added');
        }

        return response()->json([
            'data' => $listItem,
        ]);
    }


    public function checked(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);

        // Find the latest record with the same name in the same list
        $latestItem = GroceryListItem::where('name', $listItem->name)
            ->where('list_id', $listItem->list_id)
            ->orderByDesc('id')
            ->first();

        if ($latestItem) {
            $wasChecked = (bool) $latestItem->checked;
            $latestItem->checked = $request->get('checked', false);
            $latestItem->save();

            if (!$wasChecked && $request->boolean('checked')) {
                $this->notifyChecked((int) $latestItem->list_id);
            }
        }

        return response()->json([
            'data' => $latestItem ?? $listItem,
        ]);
    }

    // The last open item checked off: "finished shopping" right away, which replaces the pending
    // "checked off N items" push. Otherwise the check joins that grouped push.
    private function notifyChecked(int $listId): void
    {
        $list = GroceryList::withoutGlobalScopes()->find($listId);
        if (!$list) {
            return;
        }

        if ($list->hasOpenItems()) {
            SendListActivityPush::record($list->id, auth()->id(), 'items_checked');
            return;
        }

        SendListActivityPush::cancel($list->id, auth()->id(), 'items_checked');
        foreach ($list->memberIds() as $memberId) {
            if ($memberId === auth()->id()) {
                continue;
            }

            SendPushNotification::dispatch(
                $memberId,
                'list_completed',
                ['user' => auth()->user()->name, 'list' => $list->name],
                ['url' => '/list/' . $list->id],
            );
        }
    }

    public function update(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);
        $data = $request->input('item', []);
        // Only what the request contains: the app sends just the fields that were changed, so an edit made with an
        // older copy of the item (someone else changed the price meanwhile) can't wipe that price. A field sent as
        // null does count: that's a price being cleared. The website sends every field, as before.
        if (array_key_exists('name', $data)) {
            $listItem->name = ucfirst($data['name']);
        }
        if (array_key_exists('quantity', $data)) {
            $listItem->quantity = $data['quantity'];
        }
        if (array_key_exists('unit_price', $data)) {
            $listItem->unit_price = $data['unit_price'];
        }

        $listItem->save();

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function increase(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);
        $request->validate(['amount' => 'sometimes|integer|min:1|max:1000']);
        $listItem->increment('quantity', (int) $request->get('amount', 1));

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function decrease(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);
        $request->validate(['amount' => 'sometimes|integer|min:1|max:1000']);
        $amount = (int) $request->get('amount', 1);

        if ($listItem->quantity - $amount <= 0) {
            $listItem->delete();
        } else {
            $listItem->decrement('quantity', $amount);
        }

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function delete(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);
        $listItem->delete();

        return response()->json([
            'message' => 'Lijstitem succesvol verwijderd',
        ]);
    }

    /**
     * Moves several products to another list of the user's, e.g. to the list of a shop where they're cheaper. Each
     * goes there like adding it (an open product of that name gets the quantity added, otherwise it's a new row with
     * its price) and leaves its own list like deleting it. Only products on lists the user may see.
     */
    public function move(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'list_id' => 'required|integer',
        ]);
        $this->ensureListAccess($data['list_id']);
        $targetId = (int) $data['list_id'];

        $items = $this->accessibleItems($data['ids'])->reject(fn (GroceryListItem $item) => (int) $item->list_id === $targetId);
        DB::transaction(function () use ($items, $targetId) {
            foreach ($items as $item) {
                $latestItem = GroceryListItem::where('list_id', $targetId)
                    ->where('name', $item->name)
                    ->orderByDesc('id')
                    ->first();

                if ($latestItem && !$latestItem->checked) {
                    $latestItem->increment('quantity', $item->quantity);
                } else {
                    GroceryListItem::create([
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'list_id' => $targetId,
                        'unit_price' => $item->unit_price ?? $latestItem?->unit_price,
                    ]);
                }
                $item->delete();
            }
        });

        if ($items->isNotEmpty()) {
            SendListActivityPush::record($targetId, auth()->id(), 'items_added');
        }

        return response()->json([
            'moved' => $items->count(),
        ]);
    }

    // Deletes several products at once, like one by one; only on lists the user may see
    public function deleteMany(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $items = $this->accessibleItems($data['ids']);
        GroceryListItem::whereKey($items->modelKeys())->delete();

        return response()->json([
            'deleted' => $items->count(),
        ]);
    }

    // The products among the ids that are on a list the user may see (GroceryList's global scope)
    private function accessibleItems(array $ids): Collection
    {
        return GroceryListItem::whereKey($ids)
            ->whereIn('list_id', GroceryList::query()->select('id'))
            ->orderBy('id')
            ->get();
    }

    // Items can only be touched on lists the user may see: their own or one they accepted an invite for.
    // GroceryList's global scope applies exactly that rule, so a list someone else owns isn't found.
    private function ensureListAccess($listId): void
    {
        abort_unless(
            $listId && GroceryList::whereKey($listId)->exists(),
            403,
            'Je hebt geen toegang tot deze lijst'
        );
    }
}
