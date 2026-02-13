<?php

namespace App\Http\Controllers;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class AdminStatsController extends Controller
{
    public function users(): JsonResponse
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        $currentData = $this->getUsersData($currentMonth);
        $previousData = $this->getUsersData($previousMonth);

        return response()->json([
            'current_month' => [
                'period' => $currentMonth->format('Y-m'),
                'value' => $currentData['total'],
                'breakdown' => $currentData,
            ],
            'previous_month' => [
                'period' => $previousMonth->format('Y-m'),
                'value' => $previousData['total'],
                'breakdown' => $previousData,
            ],
            'change' => $this->calculateChange($currentData['total'], $previousData['total']),
        ]);
    }

    public function items(): JsonResponse
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        $currentData = $this->getItemsData($currentMonth);
        $previousData = $this->getItemsData($previousMonth);

        return response()->json([
            'current_month' => [
                'period' => $currentMonth->format('Y-m'),
                'value' => $currentData['added'],
                'breakdown' => $currentData,
            ],
            'previous_month' => [
                'period' => $previousMonth->format('Y-m'),
                'value' => $previousData['added'],
                'breakdown' => $previousData,
            ],
            'change' => $this->calculateChange($currentData['added'], $previousData['added']),
        ]);
    }

    public function lists(): JsonResponse
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        $currentData = $this->getListsData($currentMonth);
        $previousData = $this->getListsData($previousMonth);

        return response()->json([
            'current_month' => [
                'period' => $currentMonth->format('Y-m'),
                'value' => $currentData['created'],
                'breakdown' => $currentData,
            ],
            'previous_month' => [
                'period' => $previousMonth->format('Y-m'),
                'value' => $previousData['created'],
                'breakdown' => $previousData,
            ],
            'change' => $this->calculateChange($currentData['created'], $previousData['created']),
        ]);
    }

    public function activity(): JsonResponse
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        return response()->json([
            'current_month' => [
                'period' => $currentMonth->format('Y-m'),
                'daily' => $this->getDailyActivity($currentMonth),
                'top_lists' => $this->getTopLists($currentMonth),
            ],
            'previous_month' => [
                'period' => $previousMonth->format('Y-m'),
                'daily' => $this->getDailyActivity($previousMonth),
                'top_lists' => $this->getTopLists($previousMonth),
            ],
        ]);
    }

    public function versions(): JsonResponse
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        $currentData = $this->getVersionsData($currentMonth);
        $previousData = $this->getVersionsData($previousMonth);

        $currentOnLatest = $currentData['on_latest']['percentage'] ?? 0;
        $previousOnLatest = $previousData['on_latest']['percentage'] ?? 0;

        return response()->json([
            'current_month' => [
                'period' => $currentMonth->format('Y-m'),
                ...$currentData,
            ],
            'previous_month' => [
                'period' => $previousMonth->format('Y-m'),
                ...$previousData,
            ],
            'change' => [
                'absolute' => round($currentOnLatest - $previousOnLatest, 1),
                'percentage' => null,
            ],
        ]);
    }

    public function topItems(): JsonResponse
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        return response()->json([
            'current_month' => [
                'period' => $currentMonth->format('Y-m'),
                'most_added' => $this->getMostAddedItems($currentMonth),
                'most_checked' => $this->getMostCheckedItems($currentMonth),
            ],
            'previous_month' => [
                'period' => $previousMonth->format('Y-m'),
                'most_added' => $this->getMostAddedItems($previousMonth),
                'most_checked' => $this->getMostCheckedItems($previousMonth),
            ],
        ]);
    }

    private function getMostAddedItems(Carbon $month, ?array $listIds = null): array
    {
        $query = GroceryListItem::selectRaw('LOWER(name) as name, COUNT(*) as count, COUNT(DISTINCT list_id) as lists_count')
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month);

        if ($listIds !== null) {
            $query->whereIn('list_id', $listIds);
        }

        return $query->groupBy(DB::raw('LOWER(name)'))
            ->orderByDesc('count')
            ->limit($listIds !== null ? 5 : 10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => ucfirst($item->name),
                    'count' => (int) $item->count,
                    'lists_count' => (int) $item->lists_count,
                ];
            })
            ->toArray();
    }

    private function getMostCheckedItems(Carbon $month, ?array $listIds = null): array
    {
        $query = GroceryListItem::selectRaw('LOWER(name) as name, COUNT(*) as count')
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month);

        if ($listIds !== null) {
            $query->whereIn('list_id', $listIds);
        }

        return $query->groupBy(DB::raw('LOWER(name)'))
            ->orderByDesc('count')
            ->limit($listIds !== null ? 5 : 10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => ucfirst($item->name),
                    'count' => (int) $item->count,
                ];
            })
            ->toArray();
    }

    public function usersList(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'created_at', 'email_verified_at', 'accepted_terms_version')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($user) {
                $lastActive = PersonalAccessToken::where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id)
                    ->orderByDesc('last_used_at')
                    ->value('last_used_at');

                $listsCount = GroceryList::withoutGlobalScopes()
                    ->where('created_by', $user->id)
                    ->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'email_verified' => $user->email_verified_at !== null,
                    'terms_version' => $user->accepted_terms_version,
                    'last_active' => $lastActive,
                    'lists_count' => $listsCount,
                ];
            });

        return response()->json([
            'users' => $users,
            'total' => $users->count(),
        ]);
    }

    public function userDetail(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        $lastActive = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_used_at')
            ->value('last_used_at');

        $ownedLists = GroceryList::withoutGlobalScopes()
            ->where('created_by', $user->id)
            ->get();

        $ownedListIds = $ownedLists->pluck('id');

        $sharedListIds = GroceryListInvites::where('user_id', $user->id)
            ->where('status', GroceryListInvitesStatus::ACCEPTED)
            ->pluck('grocery_list_id');

        $allAccessibleListIds = $ownedListIds->merge($sharedListIds)->unique();

        $currentMonthItems = GroceryListItem::whereIn('list_id', $allAccessibleListIds)
            ->whereYear('created_at', $currentMonth->year)
            ->whereMonth('created_at', $currentMonth->month)
            ->count();

        $previousMonthItems = GroceryListItem::whereIn('list_id', $allAccessibleListIds)
            ->whereYear('created_at', $previousMonth->year)
            ->whereMonth('created_at', $previousMonth->month)
            ->count();

        $currentMonthChecked = GroceryListItem::whereIn('list_id', $allAccessibleListIds)
            ->where('checked', true)
            ->whereYear('updated_at', $currentMonth->year)
            ->whereMonth('updated_at', $currentMonth->month)
            ->count();

        $previousMonthChecked = GroceryListItem::whereIn('list_id', $allAccessibleListIds)
            ->where('checked', true)
            ->whereYear('updated_at', $previousMonth->year)
            ->whereMonth('updated_at', $previousMonth->month)
            ->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'terms_version' => $user->accepted_terms_version,
                'last_active' => $lastActive,
            ],
            'lists' => [
                'owned' => $ownedLists->count(),
                'shared_with_user' => $sharedListIds->count(),
                'total_access' => $allAccessibleListIds->count(),
            ],
            'items' => [
                'current_month' => [
                    'period' => $currentMonth->format('Y-m'),
                    'added' => $currentMonthItems,
                    'checked' => $currentMonthChecked,
                ],
                'previous_month' => [
                    'period' => $previousMonth->format('Y-m'),
                    'added' => $previousMonthItems,
                    'checked' => $previousMonthChecked,
                ],
                'change' => $this->calculateChange($currentMonthItems, $previousMonthItems),
            ],
        ]);
    }

    private function getUsersData(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();
        $startOfMonth = $month->copy()->startOfMonth();

        $total = User::where('created_at', '<=', $endOfMonth)->count();

        $newRegistrations = User::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count();

        $thirtyDaysAgo = $endOfMonth->copy()->subDays(30);
        $active = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('last_used_at', '>=', $thirtyDaysAgo)
            ->where('last_used_at', '<=', $endOfMonth)
            ->distinct('tokenable_id')
            ->count('tokenable_id');

        $verifiedEmail = User::where('created_at', '<=', $endOfMonth)
            ->whereNotNull('email_verified_at')
            ->count();

        return [
            'total' => $total,
            'new_registrations' => $newRegistrations,
            'active' => $active,
            'verified_email' => $verifiedEmail,
        ];
    }

    private function getItemsData(Carbon $month): array
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $added = GroceryListItem::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count();

        $checked = GroceryListItem::where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->count();

        $thirtyDaysAgo = $endOfMonth->copy()->subDays(30);
        $activeUserIds = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('last_used_at', '>=', $thirtyDaysAgo)
            ->where('last_used_at', '<=', $endOfMonth)
            ->distinct()
            ->pluck('tokenable_id');

        $activeUserCount = $activeUserIds->count();
        $avgPerUser = $activeUserCount > 0 ? round($added / $activeUserCount, 1) : 0;

        $listCount = GroceryList::withoutGlobalScopes()
            ->where('created_at', '<=', $endOfMonth)
            ->count();
        $avgPerList = $listCount > 0 ? round($added / $listCount, 1) : 0;

        return [
            'added' => $added,
            'checked' => $checked,
            'avg_per_user' => $avgPerUser,
            'avg_per_list' => $avgPerList,
        ];
    }

    private function getListsData(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();

        $created = GroceryList::withoutGlobalScopes()
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count();

        $shared = GroceryList::withoutGlobalScopes()
            ->whereHas('groceryListInvites', function ($query) {
                $query->where('status', GroceryListInvitesStatus::ACCEPTED);
            })
            ->where('created_at', '<=', $endOfMonth)
            ->count();

        $totalItems = GroceryListItem::where('created_at', '<=', $endOfMonth)->count();
        $totalLists = GroceryList::withoutGlobalScopes()
            ->where('created_at', '<=', $endOfMonth)
            ->count();
        $avgItemsPerList = $totalLists > 0 ? round($totalItems / $totalLists, 1) : 0;

        $sharedListIds = GroceryList::withoutGlobalScopes()
            ->whereHas('groceryListInvites', function ($query) {
                $query->where('status', GroceryListInvitesStatus::ACCEPTED);
            })
            ->where('created_at', '<=', $endOfMonth)
            ->pluck('id');

        $totalMembers = 0;
        if ($sharedListIds->count() > 0) {
            $totalMembers = GroceryListInvites::whereIn('grocery_list_id', $sharedListIds)
                ->where('status', GroceryListInvitesStatus::ACCEPTED)
                ->count();
            $totalMembers += $sharedListIds->count();
        }

        $avgMembersPerSharedList = $sharedListIds->count() > 0
            ? round($totalMembers / $sharedListIds->count(), 1)
            : 0;

        return [
            'created' => $created,
            'shared' => $shared,
            'avg_items_per_list' => $avgItemsPerList,
            'avg_members_per_shared_list' => $avgMembersPerSharedList,
        ];
    }

    private function getDailyActivity(Carbon $month): array
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $dailyAdded = GroceryListItem::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as items_added')
            )
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $dailyChecked = GroceryListItem::select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw('COUNT(*) as items_checked')
            )
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $current = $startOfMonth->copy();
        $today = Carbon::now();

        while ($current <= $endOfMonth && $current <= $today) {
            $dateStr = $current->format('Y-m-d');
            $result[] = [
                'date' => $dateStr,
                'items_added' => $dailyAdded->get($dateStr)?->items_added ?? 0,
                'items_checked' => $dailyChecked->get($dateStr)?->items_checked ?? 0,
            ];
            $current->addDay();
        }

        return $result;
    }

    private function getTopLists(Carbon $month): array
    {
        return GroceryList::withoutGlobalScopes()
            ->select('grocery_lists.id', 'grocery_lists.name')
            ->selectRaw('COUNT(grocery_list_items.id) as items_added')
            ->leftJoin('grocery_list_items', function ($join) use ($month) {
                $join->on('grocery_lists.id', '=', 'grocery_list_items.list_id')
                    ->whereYear('grocery_list_items.created_at', $month->year)
                    ->whereMonth('grocery_list_items.created_at', $month->month);
            })
            ->groupBy('grocery_lists.id', 'grocery_lists.name')
            ->orderByDesc('items_added')
            ->limit(10)
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'items_added' => (int) $list->items_added,
                ];
            })
            ->toArray();
    }

    private function getVersionsData(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();

        $latestVersion = User::where('created_at', '<=', $endOfMonth)
            ->whereNotNull('accepted_terms_version')
            ->orderByDesc('accepted_terms_version')
            ->value('accepted_terms_version');

        $versionCounts = User::select('accepted_terms_version', DB::raw('COUNT(*) as count'))
            ->where('created_at', '<=', $endOfMonth)
            ->whereNotNull('accepted_terms_version')
            ->groupBy('accepted_terms_version')
            ->get();

        $total = $versionCounts->sum('count');

        $breakdown = [];
        $onLatestCount = 0;

        foreach ($versionCounts as $version) {
            $percentage = $total > 0 ? round(($version->count / $total) * 100, 1) : 0;
            $breakdown[$version->accepted_terms_version] = [
                'count' => $version->count,
                'percentage' => $percentage,
            ];

            if ($version->accepted_terms_version === $latestVersion) {
                $onLatestCount = $version->count;
            }
        }

        return [
            'latest_version' => $latestVersion,
            'breakdown' => $breakdown,
            'on_latest' => [
                'count' => $onLatestCount,
                'percentage' => $total > 0 ? round(($onLatestCount / $total) * 100, 1) : 0,
            ],
        ];
    }

    private function calculateChange(int $current, int $previous): array
    {
        $absolute = $current - $previous;
        $percentage = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;

        return [
            'absolute' => $absolute,
            'percentage' => $percentage,
        ];
    }
}
