<?php

namespace App\Http\Controllers;

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

        return response()->json([
            'data' => $listItem,
        ]);
    }


    public function checked(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        // Find the latest record with the same name in the same list
        $latestItem = GroceryListItem::where('name', $listItem->name)
            ->where('list_id', $listItem->list_id)
            ->orderByDesc('id')
            ->first();

        if ($latestItem) {
            $latestItem->checked = $request->get('checked', false);
            $latestItem->save();
        }

        return response()->json([
            'data' => $latestItem ?? $listItem,
        ]);
    }

    public function update(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
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
        $listItem->increment('quantity', $request->get('amount', 1));

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function decrease(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
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
        $listItem->delete();

        return response()->json([
            'message' => 'Lijstitem succesvol verwijderd',
        ]);
    }
}
