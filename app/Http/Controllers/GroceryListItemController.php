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
        $listItems = GroceryListItem::select('grocery_list_items.*');

        $listItems->join('grocery_lists', 'grocery_lists.id', '=', 'grocery_list_items.list_id')
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
        $listItems->groupBy('grocery_list_items.id', 'grocery_list_items.name', 'grocery_list_items.quantity', 'grocery_list_items.checked', 'grocery_list_items.list_id', 'grocery_list_items.created_at', 'grocery_list_items.updated_at', 'grocery_list_items.unit_price');
        return response()->json([
            'data' => $listItems->get(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $listItem = GroceryListItem::where('name', $data['name'])
            ->where('list_id', $data['list_id'])
            ->first();

        if ($listItem) {
            $listItem->checked = false;
            $listItem->quantity = 1;
            $listItem->save();
        } else {
            $listItem = GroceryListItem::create(
                [
                    'name' => ucfirst($data['name']),
                    'quantity' => $data['quantity'] ?? 1,
                    'list_id' => $data['list_id'] ?? null,
                    'created_by' => $data['created_by'] ?? 1,
                ]
            );
        }

        return response()->json([
            'data' => $listItem,
        ]);
    }


    public function checked(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $listItem->checked = $request->get('checked', false);
        $listItem->save();

        return response()->json([
            'data' => $listItem,
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
