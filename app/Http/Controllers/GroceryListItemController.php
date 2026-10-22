<?php

namespace App\Http\Controllers;

use App\Jobs\SendListActivityPush;
use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Http\Request;

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

        // Subquery to get latest item ID per name in each list
        $latestIdsSubquery = GroceryListItem::selectRaw('MAX(id) as id')
            ->when($listId, function ($query) use ($listId) {
                $query->where('list_id', $listId);
            })
            ->groupBy('list_id', 'name');

        $listItems = GroceryListItem::select('grocery_list_items.*')
            ->joinSub($latestIdsSubquery, 'latest', function ($join) {
                $join->on('grocery_list_items.id', '=', 'latest.id');
            })
            ->join('grocery_lists', 'grocery_lists.id', '=', 'grocery_list_items.list_id')
            ->leftJoin('grocery_list_invites', 'grocery_list_invites.grocery_list_id', '=', 'grocery_lists.id')
            ->where(function ($subQuery) {
                $subQuery->where('grocery_lists.created_by', auth()->user()->id)
                    ->orWhere(function ($query) {
                        $query->where('grocery_list_invites.user_id', '=', auth()->user()->id)
                            ->where('grocery_list_invites.status', 'accepted');
                    });
            });

        if (isset($listId)) {
            $listItems->where('grocery_list_items.list_id', $listId);
        }

        if (isset($offset) && isset($limit)) {
            $listItems->limit($limit)
                ->offset($offset);
        }

        $listItems->groupBy(
            'grocery_list_items.id',
            'grocery_list_items.name',
            'grocery_list_items.quantity',
            'grocery_list_items.checked',
            'grocery_list_items.list_id',
            'grocery_list_items.created_at',
            'grocery_list_items.updated_at',
            'grocery_list_items.unit_price',
            'grocery_list_items.created_by',
            'grocery_list_items.updated_by',

        );

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
        $data = $request->all()['item'];
        $listItem->name = ucfirst($data['name']);
        $listItem->quantity = $data['quantity'];
        $listItem->unit_price = $data['unit_price'];

        $listItem->save();

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function increase(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);
        $listItem->increment('quantity', $request->get('amount', 1));

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function decrease(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $this->ensureListAccess($listItem->list_id);
        $amount = $request->get('amount', 1);

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
