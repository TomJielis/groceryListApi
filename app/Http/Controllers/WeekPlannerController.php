<?php

namespace App\Http\Controllers;

use App\Http\Requests\WeekPlanner\WeekPlannerAddRequest;
use App\Http\Requests\WeekPlanner\WeekPlannerSettingRequest;
use App\Http\Requests\WeekPlanner\WeekPlannerShowRequest;
use App\Http\Resources\GroceryListItemResource;
use App\Http\Resources\WeekPlannerProductResource;
use App\Http\Resources\WeekPlannerSettingResource;
use App\Services\GroceryListItemService;
use App\Queries\GroceryList\WeekPlannerQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The week groceries planner: shows what a list gets every week (see WeekPlannerQuery), and adds the products the user
 * ticked in one go. Each list has its own history, so everything here is per list. The requests answer 403 for a list
 * the user may not see.
 */
class WeekPlannerController extends Controller
{
    public function show(WeekPlannerShowRequest $request, int $id, WeekPlannerQuery $planner): AnonymousResourceCollection
    {
        return WeekPlannerProductResource::collection($planner->forList($id, null, $request->timezone()))
            ->additional(['weeks' => WeekPlannerQuery::WEEKS]);
    }

    // Adds the ticked products like adding them one by one (GroceryListItemService::addProducts)
    public function add(WeekPlannerAddRequest $request, int $id, GroceryListItemService $items): AnonymousResourceCollection
    {
        return GroceryListItemResource::collection($items->addProducts($id, $request->products(), $request->user()));
    }

    // Switches the planner button in the app on or off for this user
    public function setting(WeekPlannerSettingRequest $request): WeekPlannerSettingResource
    {
        $user = $request->user();
        $user->week_planner = $request->enabled();
        $user->save();

        return new WeekPlannerSettingResource($user);
    }
}
