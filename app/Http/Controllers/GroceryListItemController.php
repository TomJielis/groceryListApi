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
        $listItems = GroceryListItem::select('list_items.*');

        $listItems->join('grocery_lists', 'grocery_lists.id', '=', 'list_items.list_id')
            ->leftJoin('grocery_list_invites', 'grocery_list_invites.grocery_list_id', '=', 'grocery_lists.id')

            ->where(function ($subQuery) {
                $subQuery->where('grocery_lists.created_by', auth()->user()->id)
                    ->orWhere(function($query){
                        $query->where('grocery_list_invites.user_id','=', auth()->user()->id)
                           ->where('grocery_list_invites.status', 'accepted');
                    });
            });

        $listItems->where('list_items.checked',false);

        if(isset($listId)){
            $listItems->where('list_items.list_id', $listId);
        }

        if(isset($offset) && isset($limit)){
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
        $listItem = GroceryListItem::create(
            [
                'name' => $data['name'],
                'quantity' => $data['quantity'] ?? 1,
                'list_id' => $data['list_id'] ?? null,
                'created_by' => $data['created_by'] ?? 1,
            ]
        );

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
        $listItem->decrement('quantity', $amount);

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function delete(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
    {
        $listItem->delete();

        return response()->json([
            'message' => 'List item deleted successfully',
        ]);
    }
}
