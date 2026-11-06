<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
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
        // Items are versioned per name (a new row per change); the current item is the latest row of each name.
        // The two counts above include every version and stay as they are for the web app;
        // open_items_count is what is still to be bought right now (used by the mobile app).
        $latestIds = GroceryListItem::selectRaw('MAX(id)')
            ->whereColumn('list_id', 'grocery_lists.id')
            ->groupBy('name');
        $openItemsCount = GroceryListItem::selectRaw('COUNT(*)')
            ->whereColumn('list_id', 'grocery_lists.id')
            ->where('checked', false)
            ->whereIn('id', $latestIds);

        $listItems = GroceryList::select('*')
            ->selectSub($openItemsCount, 'open_items_count')
            ->withCount('groceryListItems')
            ->withCount('groceryListItemsChecked')
            ->with([
                // Everyone who joined (the owner is createdBy)
                'groceryListInvites' => fn ($query) => $query->where('status', GroceryListInvitesStatus::ACCEPTED)->with('user'),
                'createdBy',
            ]);

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
        $data['created_by'] = auth()->user()?->id;

        $listItem = GroceryList::create(
            [
                'name' => $data['name'],
                'created_by' => $data['created_by'] ?? 1,
            ]
        );

        return response()->json([
            'data' => $listItem,
        ]);
    }

    public function update(Request $request, GroceryList $groceryList): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $groceryList->name = $data['name'] ?? $groceryList->name;
        $groceryList->save();

        return response()->json([
            'data' => $groceryList,
        ]);
    }

    public function unshare(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        if(!isset($data['groceryListId']) || !isset($data['userId'])){
            return response()->json(['message' => 'Ongeldige aanvraag'], 400);
        }

        // Only someone with access to the list (owner or member) can remove people from it
        if (!GroceryList::find($data['groceryListId'])) {
            return response()->json(['message' => 'Boodschappen lijst niet gevonden'], 404);
        }

        $invites = GroceryListInvites::where('grocery_list_id', $data['groceryListId'])
            ->where('user_id', $data['userId']);
        // Only someone who actually joined is told
        $wasMember = (clone $invites)->where('status', GroceryListInvitesStatus::ACCEPTED)->exists();
        $invites->delete();

        $groceryList = GroceryList::withoutGlobalScopes()->find($data['groceryListId']);
        if ($wasMember && $groceryList && (int) $data['userId'] !== auth()->id()) {
            SendPushNotification::dispatch(
                (int) $data['userId'],
                'list_removed',
                ['user' => auth()->user()->name, 'list' => $groceryList->name],
                ['url' => '/'],
            );
        }

        return response()->json([
            'message' => 'Toegang tot de lijst is ingetrokken.',
        ]);
    }

    public function favorite(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $listId = $request->get('listId');
        // Only a list the user can see can be their favorite (null clears it)
        if ($listId && !GroceryList::find($listId)) {
            return response()->json(['message' => 'Boodschappen lijst niet gevonden'], 404);
        }
        $user->favorite_list_id = $listId ?? null;
        $user->save();
        return response()->json([
            'message' => 'Lijst is gekenmerkt als favoriet',
        ]);
    }

    public function delete(Request $request, GroceryList $groceryList): \Illuminate\Http\JsonResponse
    {

        if($groceryList->created_by !== auth()->id()) {

            $groceryListInvite = GroceryListInvites::where('grocery_list_id', $groceryList->id)
                ->where('user_id', auth()->id())
                ->where('status', '=', GroceryListInvitesStatus::ACCEPTED)
                ->first();

            $groceryListInvite->delete();

            return response()->json([
                'message' => 'Lijst is verwijderd.',
            ]);
        }


        // Collected before the invites are gone
        $memberIds = array_diff($groceryList->memberIds(), [auth()->id()]);

        $groceryList->groceryListInvites()->delete();
        $groceryList->groceryListItems()->delete();
        $groceryList->delete();

        foreach ($memberIds as $memberId) {
            SendPushNotification::dispatch(
                $memberId,
                'list_deleted',
                ['user' => auth()->user()->name, 'list' => $groceryList->name],
                ['url' => '/'],
            );
        }

        return response()->json([
            'message' => 'Lijstitem is verwijderd.',
        ]);
    }
}
