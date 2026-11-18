<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserStatsRequest;
use App\Http\Resources\UserStatsResource;
use App\Queries\UserStats\UserStatsQuery;

// "My statistics": what the user added, checked off and spent this month (or a chosen one) next to the month before
class UserStatsController extends Controller
{
    public function stats(UserStatsRequest $request, UserStatsQuery $stats): UserStatsResource
    {
        return new UserStatsResource($stats->forUser($request->user(), $request->month()));
    }
}
