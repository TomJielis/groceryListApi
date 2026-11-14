<?php

namespace App\Http\Controllers;

use App\Jobs\SendListActivityPush;
use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\User;
use App\Services\WeekPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The week groceries planner: shows what a list gets every week (see WeekPlanner), and adds the products the user
 * ticked in one go. Each list has its own history, so everything here is per list.
 */
class WeekPlannerController extends Controller
{
    // Like moving products (GroceryListItemController::move); the app sends all ticked products in one go
    private const MAX_PRODUCTS = 500;
    // The planner's stepper in the app stops at 99 as well
    private const MAX_QUANTITY = 99;

    // ?timezone=Europe/Amsterdam: the phone's time zone, so a week is Monday to Sunday where the user is
    public function show(Request $request, int $id, WeekPlanner $planner): JsonResponse
    {
        $this->ensureListAccess($id);
        $request->validate(['timezone' => 'nullable|string|timezone:all']);

        return response()->json([
            'data' => $planner->forList($id, null, $request->query('timezone')),
            'weeks' => WeekPlanner::WEEKS,
        ]);
    }

    /**
     * Adds the ticked products like adding them one by one: one still open on the list gets the quantity added to it,
     * anything else becomes a new item with the price it had last time. The others on the list get one grouped push.
     */
    public function add(Request $request, int $id): JsonResponse
    {
        $this->ensureListAccess($id);
        $data = $request->validate([
            'items' => 'required|array|min:1|max:' . self::MAX_PRODUCTS,
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:' . self::MAX_QUANTITY,
        ]);

        $items = DB::transaction(fn () => collect($data['items'])->map(function (array $item) use ($id) {
            $latestItem = GroceryListItem::where('list_id', $id)
                ->where('name', $item['name'])
                ->orderByDesc('id')
                ->first();

            if ($latestItem && !$latestItem->checked) {
                $latestItem->increment('quantity', $item['quantity']);

                return $latestItem->fresh();
            }

            return GroceryListItem::create([
                'name' => ucfirst($item['name']),
                'quantity' => $item['quantity'],
                'list_id' => $id,
                'unit_price' => $latestItem?->unit_price,
            ]);
        }));

        SendListActivityPush::record($id, auth()->id(), 'items_added', collect($data['items'])
            ->map(fn (array $item) => ['name' => ucfirst($item['name']), 'quantity' => (int) $item['quantity']])
            ->all());

        return response()->json(['data' => $items->values()]);
    }

    // Switches the planner button in the app on or off for this user
    public function setting(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);

        /** @var User $user */
        $user = auth()->user();
        $user->week_planner = (bool) $data['enabled'];
        $user->save();

        return response()->json(['data' => ['week_planner' => $user->week_planner]]);
    }

    // Only lists the user may see: GroceryList's global scope finds their own lists and accepted shares
    private function ensureListAccess(int $id): void
    {
        abort_unless(GroceryList::whereKey($id)->exists(), 403, __('lists.no_access', [], $this->userLanguage()));
    }
}
