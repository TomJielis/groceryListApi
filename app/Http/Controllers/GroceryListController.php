<?php

namespace App\Http\Controllers;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\User;
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
            ->withCount('groceryListItemsChecked')
            ->with(['groceryListInvites.user', 'createdBy']);

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

    public function share(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $groceryList = GroceryList::find($data['groceryListId']);

        if (!$groceryList) {
            return response()->json(['message' => 'Boodschappen lijst niet gevonden'], 404);
        }

        $user = User::where('email','=', $data['email'])->first();
        if(!$user){
            return response()->json(['message' => 'Gebruiker niet gevonden'], 404);
        }

        if($user->id === auth()->user()->id){
            return response()->json(['message' => 'Je kan de lijst niet met jezelf delen'], 400);
        }

        GroceryListInvites::create(
            [
                'grocery_list_id' => $groceryList->id,
                'user_id' => $user->id,
                'status' => GroceryListInvitesStatus::ACCEPTED,
            ]
        );



        return response()->json([
            'message' => 'Boodschappenlijst is gedeeld.',
            'data' => $groceryList,
        ]);
    }

    public function favorite(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $user->favorite_list_id = $request->get('listId') ?? null;
        $user->save();
        return response()->json([
            'message' => 'Lijst is gekenmerkt als favoriet',
        ]);
    }

    public function delete(Request $request, GroceryList $groceryList): \Illuminate\Http\JsonResponse
    {
        $groceryList->groceryListInvites()->delete();
        $groceryList->groceryListItems()->delete();
        $groceryList->delete();

        return response()->json([
            'message' => 'Lijstitem is verwijderd.',
        ]);
    }
}
