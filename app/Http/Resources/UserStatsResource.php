<?php

namespace App\Http\Resources;

use App\Data\UserStats;
use App\Data\UserStatsMonth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "My statistics" (UserStatsQuery): the chosen month next to the one before. Without a data wrapper, as the app and the
 * website have always read it.
 *
 * @mixin UserStats
 */
class UserStatsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $request->user()->language;
        $both = fn (callable $figures) => [
            'current_month' => $figures($this->current),
            'previous_month' => $figures($this->previous),
        ];

        return [
            'items' => $both(fn (UserStatsMonth $month) => [
                'added' => $month->added,
                'checked' => $month->checked,
                'period' => $this->period($month->month, $language),
            ]),
            'spend' => $both(fn (UserStatsMonth $month) => [
                'total'   => $month->spendTotal,
                'by_list' => $month->spendByList,
            ]),
            'user_breakdown' => $both(fn (UserStatsMonth $month) => $month->userBreakdown),
            'top_items' => [
                'current_month' => [
                    'most_added' => $this->mostAdded,
                    'most_checked' => $this->mostChecked,
                ],
            ],
            // Their recipes and cookbooks: added in the month (and the one before), and how many in all
            'recipes' => $this->recipes,
            'available_months' => $this->availableMonths,
            'invalid_login_attempts' => $both(fn (UserStatsMonth $month) => $month->invalidLoginAttempts),
        ];
    }

    // "november 2026" / "November 2026", in the user's language
    private function period(Carbon $month, ?string $language): string
    {
        return $month->locale($language ?? 'en')->translatedFormat('F Y');
    }
}
