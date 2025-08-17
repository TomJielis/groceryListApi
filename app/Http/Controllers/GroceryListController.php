<?php

namespace App\Http\Controllers;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Http\Request;

class GroceryListController extends Controller
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
        $listItems = GroceryList::select('*')
            ->withCount('groceryListItems')
            ->withCount('groceryListItemsChecked');

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
        $data['created_by'] = auth()->user()->id; // Assuming you want to associate the list with the authenticated user

        $listItem = GroceryList::create(
            [
                'name' => $data['name'],
                'created_by' => $data['created_by'] ?? 1, // Default to 1 if not provided
            ]
        );

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function delete(Request $request, GroceryList $groceryList): \Illuminate\Http\JsonResponse
    {
        $groceryList->delete();

        return response()->json([
            'message' => 'List item deleted successfully',
        ]);
    }
}
