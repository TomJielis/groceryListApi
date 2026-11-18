<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminStatsRequest;
use App\Http\Resources\AdminStatsResource;
use App\Http\Resources\AdminUserDetailResource;
use App\Http\Resources\AdminUserRowResource;
use App\Models\User;
use App\Queries\Admin\DailyActivityQuery;
use App\Queries\Admin\ItemStatsQuery;
use App\Queries\Admin\ListStatsQuery;
use App\Queries\Admin\PlatformUsageQuery;
use App\Queries\Admin\RecipeTotalsQuery;
use App\Queries\Admin\TermsVersionsQuery;
use App\Queries\Admin\TopListsQuery;
use App\Queries\Admin\UserActivityQuery;
use App\Queries\Admin\UserGrowthQuery;
use App\Queries\Items\SpendQuery;
use App\Queries\Items\TopItemsQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * The admin dashboard: every statistic for the chosen month and the one before (the queries are in App\Queries\Admin),
 * the list of users, one user's page, and blocking someone. Admins only (AdminMiddleware).
 */
class AdminStatsController extends Controller
{
    public function users(AdminStatsRequest $request, UserGrowthQuery $stats): AdminStatsResource
    {
        return $this->withValue($request, fn (Carbon $month) => $stats->forMonth($month), 'total');
    }

    public function items(AdminStatsRequest $request, ItemStatsQuery $stats): AdminStatsResource
    {
        return $this->withValue($request, fn (Carbon $month) => $stats->forMonth($month), 'added');
    }

    public function lists(AdminStatsRequest $request, ListStatsQuery $stats): AdminStatsResource
    {
        return $this->withValue($request, fn (Carbon $month) => $stats->forMonth($month), 'created');
    }

    // Recipes and cookbooks: added in the month, and how many there are by its end
    public function recipes(AdminStatsRequest $request, RecipeTotalsQuery $stats): AdminStatsResource
    {
        return $this->withValue($request, fn (Carbon $month) => $stats->forMonth($month), 'added');
    }

    public function activity(AdminStatsRequest $request, DailyActivityQuery $daily, TopListsQuery $topLists): AdminStatsResource
    {
        return new AdminStatsResource($request->months()->compare(fn (Carbon $month) => [
            'daily' => $daily->forMonth($month),
            'top_lists' => $topLists->forMonth($month),
        ]));
    }

    public function versions(AdminStatsRequest $request, TermsVersionsQuery $stats): AdminStatsResource
    {
        return new AdminStatsResource($request->months()->compare(fn (Carbon $month) => $stats->forMonth($month)));
    }

    public function platforms(AdminStatsRequest $request, PlatformUsageQuery $stats): AdminStatsResource
    {
        return new AdminStatsResource($request->months()->compare(fn (Carbon $month) => $stats->forMonth($month)));
    }

    public function topItems(AdminStatsRequest $request, TopItemsQuery $stats): AdminStatsResource
    {
        return new AdminStatsResource($request->months()->compare(fn (Carbon $month) => [
            'most_added' => $stats->mostAdded($month),
            'most_checked' => $stats->mostChecked($month),
        ]));
    }

    public function spend(AdminStatsRequest $request, SpendQuery $spend): AdminStatsResource
    {
        return new AdminStatsResource($request->months()->compare(fn (Carbon $month) => ['total' => $spend->total($month)]));
    }

    // Newest first
    public function usersList(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'created_at', 'email_verified_at', 'accepted_terms_version', 'blocked')
            ->withActivity()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'users' => AdminUserRowResource::collection($users),
            'total' => $users->count(),
        ]);
    }

    public function userDetail(AdminStatsRequest $request, int $id, UserActivityQuery $activity): AdminUserDetailResource|JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return new AdminUserDetailResource($user, $activity->forUser($user, $request->months()));
    }

    // Blocks someone, or unblocks them
    public function block(int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->blocked = !$user->blocked;
        $user->save();

        // Logins only check `blocked` when signing in, so end the sessions that are already open
        if ($user->blocked) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'User blocked successfully']);
    }

    /**
     * Both months, each with its figures (breakdown) and the one the dashboard shows big (value)
     *
     * @param callable(Carbon): array<string, mixed> $figures
     */
    private function withValue(AdminStatsRequest $request, callable $figures, string $value): AdminStatsResource
    {
        return new AdminStatsResource($request->months()->compare(function (Carbon $month) use ($figures, $value) {
            $breakdown = $figures($month);

            return ['value' => $breakdown[$value], 'breakdown' => $breakdown];
        }));
    }
}
