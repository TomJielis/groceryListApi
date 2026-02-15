<?php

namespace App\Http\Controllers;

use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\GroceryListItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserStatsController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = auth()->user();
        $month = $this->parseMonth($request->query('month'));
        $previousMonth = $month->copy()->subMonth();

        $ownedListIds = GroceryList::withoutGlobalScopes()
            ->where('created_by', $user->id)
            ->pluck('id');

        $sharedListIds = GroceryListInvites::where('user_id', $user->id)
            ->where('status', GroceryListInvitesStatus::ACCEPTED)
            ->pluck('grocery_list_id');

        $allAccessibleListIds = $ownedListIds->merge($sharedListIds)->unique()->toArray();

        $currentMonthAdded = $this->getItemsAddedCount($user->id, $allAccessibleListIds, $month);
        $currentMonthChecked = $this->getItemsCheckedCount($user->id, $allAccessibleListIds, $month);
        $previousMonthAdded = $this->getItemsAddedCount($user->id, $allAccessibleListIds, $previousMonth);
        $previousMonthChecked = $this->getItemsCheckedCount($user->id, $allAccessibleListIds, $previousMonth);
        $currentInvalidLoginAttempts = $user->invalidLoginAttempts()
            ->where('attempted_at', '>=', $month->copy()->startOfMonth())
            ->where('attempted_at', '<=', $month->copy()->endOfMonth())
            ->count();
        $previousInvalidLoginAttempts = $user->invalidLoginAttempts()
            ->where('attempted_at', '>=', $previousMonth->copy()->startOfMonth())
            ->where('attempted_at', '<=', $previousMonth->copy()->endOfMonth())
            ->count();

        return response()->json([
            'items' => [
                'current_month' => [
                    'added' => $currentMonthAdded,
                    'checked' => $currentMonthChecked,
                    'period' => $this->getLocalizedPeriod($month, $user->language),
                ],
                'previous_month' => [
                    'added' => $previousMonthAdded,
                    'checked' => $previousMonthChecked,
                    'period' => $this->getLocalizedPeriod($previousMonth, $user->language),
                ],
                'change' => $this->calculateChange($currentMonthAdded, $previousMonthAdded),
            ],
            'top_items' => [
                'current_month' => [
                    'most_added' => $this->getMostAddedItems($user->id, $allAccessibleListIds, $month),
                    'most_checked' => $this->getMostCheckedItems($user->id, $allAccessibleListIds, $month),
                ],
            ],
            'available_months' => $this->getAvailableMonths($user->id, $allAccessibleListIds),
            'invalid_login_attempts' => [
                'current_month' => $currentInvalidLoginAttempts,
                'previous_month' => $previousInvalidLoginAttempts,
                'change' => $this->calculateChange($currentInvalidLoginAttempts, $previousInvalidLoginAttempts),
            ]
        ]);
    }

    private function parseMonth(?string $monthString): Carbon
    {
        if ($monthString && preg_match('/^\d{4}-\d{2}$/', $monthString)) {
            return Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();
        }
        return Carbon::now()->startOfMonth();
    }

    private function getLocalizedPeriod(Carbon $month, ?string $language): string
    {
        $locale = $language ?? 'en';
        return $month->locale($locale)->translatedFormat('F Y');
    }

    private function getItemsAddedCount(int $userId, array $listIds, Carbon $month): int
    {
        return GroceryListItem::whereIn('list_id', $listIds)
            ->where('created_by', $userId)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count();
    }

    private function getItemsCheckedCount(int $userId, array $listIds, Carbon $month): int
    {
        return GroceryListItem::whereIn('list_id', $listIds)
            ->where('updated_by', $userId)
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->count();
    }

    private function getMostAddedItems(int $userId, array $listIds, Carbon $month): array
    {
        return GroceryListItem::selectRaw('LOWER(name) as name, COUNT(*) as count')
            ->whereIn('list_id', $listIds)
            ->where('created_by', $userId)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->groupBy(DB::raw('LOWER(name)'))
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst($item->name),
                'count' => (int) $item->count,
            ])
            ->toArray();
    }

    private function getMostCheckedItems(int $userId, array $listIds, Carbon $month): array
    {
        return GroceryListItem::selectRaw('LOWER(name) as name, COUNT(*) as count')
            ->whereIn('list_id', $listIds)
            ->where('updated_by', $userId)
            ->where('checked', true)
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->groupBy(DB::raw('LOWER(name)'))
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst($item->name),
                'count' => (int) $item->count,
            ])
            ->toArray();
    }

    private function getAvailableMonths(int $userId, array $listIds): array
    {
        return GroceryListItem::selectRaw("DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month")
            ->whereIn('list_id', $listIds)
            ->where('created_by', $userId)
            ->orderByDesc('month')
            ->pluck('month')
            ->toArray();
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
