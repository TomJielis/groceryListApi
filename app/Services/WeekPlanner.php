<?php

namespace App\Services;

use App\Models\GroceryListItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WeekPlanner
{
    // How far back it looks: recent enough that old habits drop out, long enough to see a rhythm
    public const WEEKS = 12;

    // Weeks run Monday to Sunday in the phone's time zone (sent by the app); this one when it sends none.
    // Only complete weeks count: the current week is still going, and half a week would make weekly products look
    // like "sometimes".
    public const TIMEZONE = 'Europe/Amsterdam';

    // What you buy now: quantities and "3× a week" come from the last weeks, so a new habit shows right away
    public const RECENT_WEEKS = 4;

    // In at least half of the weeks the list was used: a weekly product
    private const WEEKLY_SHARE = 0.5;

    // In this many of the recent weeks: weekly too, even when it's a new habit
    private const RECENT_WEEKLY = 3;

    // In at least this share of the used weeks (and in 2 weeks or more): a "sometimes" product, e.g. every 4 weeks
    private const SOMETIMES_SHARE = 0.2;

    /**
     * @return list<array{name: string, quantity: int, group: string, times_per_week: float, every_weeks: int|null,
     *                    in_list: bool, unit_price: float|null}>
     */
    public function forList(int $listId, ?CarbonImmutable $now = null, ?string $timezone = null): array
    {
        $now ??= CarbonImmutable::now();
        // Monday 00:00 of the current week; the weeks before it are counted, it is not
        $thisWeek = $now->setTimezone($timezone ?: self::TIMEZONE)->startOfWeek(CarbonImmutable::MONDAY);
        $since = $thisWeek->subWeeks(self::WEEKS);

        $rows = GroceryListItem::where('list_id', $listId)
            ->where('created_at', '>=', $since->utc())
            ->where('created_at', '<', $thisWeek->utc())
            ->orderBy('id')
            ->get(['id', 'name', 'quantity', 'unit_price', 'created_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $weekOf = fn(GroceryListItem $row) => $this->weekIndex($row, $thisWeek);
        $usedWeeks = $rows->map($weekOf)->unique();
        $weeks = max(1, $usedWeeks->count());
        $recentWeeks = max(1, $usedWeeks->filter(fn(int $week) => $week <= self::RECENT_WEEKS)->count());

        $open = $this->openNames($listId);

        $products = $rows
            ->groupBy(fn(GroceryListItem $row) => mb_strtolower(trim($row->name)))
            ->map(fn(Collection $group, string $key) => $this->product($group, $key, $weeks, $recentWeeks, $thisWeek, $open))
            ->filter()
            ->values()
            ->all();

        usort($products, function (array $a, array $b) {
            // Weekly first; within a group the most frequent first, then by name
            if ($a['group'] !== $b['group']) {
                return $a['group'] === 'weekly' ? -1 : 1;
            }

            return [$b['times_per_week'], $a['name']] <=> [$a['times_per_week'], $b['name']];
        });

        return $products;
    }

    /**
     * @param int $weeks Weeks the list was used in the last 12
     * @param int $recentWeeks Weeks the list was used in the last 4
     */
    private function product(
        Collection      $rows,
        string          $key,
        int             $weeks,
        int             $recentWeeks,
        CarbonImmutable $thisWeek,
        array           $open,
    ): ?array
    {
        $weekIndexes = $rows->map(fn(GroceryListItem $row) => $this->weekIndex($row, $thisWeek));
        $weeksWithIt = $weekIndexes->unique()->count();
        $share = $weeksWithIt / $weeks;

        $recent = $rows->filter(fn(GroceryListItem $row) => $this->weekIndex($row, $thisWeek) <= self::RECENT_WEEKS);
        $recentWeeksWithIt = $weekIndexes->filter(fn(int $week) => $week <= self::RECENT_WEEKS)->unique()->count();

        if (($weeksWithIt >= 2 && $share >= self::WEEKLY_SHARE) || $recentWeeksWithIt >= self::RECENT_WEEKLY) {
            $group = 'weekly';
        } elseif ($weeksWithIt >= 2 && $share >= self::SOMETIMES_SHARE) {
            $group = 'sometimes';
        } else {
            return null;
        }

        $quantityOf = fn(Collection $set) => (int)$set->sum(fn(GroceryListItem $row) => max(1, (int)$row->quantity));
        $latest = $rows->last();

        [$perWeekRows, $perWeekWeeks] = $recent->isNotEmpty() ? [$recent, $recentWeeks] : [$rows, $weeks];

        return [
            'name' => $latest->name,
            'quantity' => max(1, (int)round($group === 'weekly'
                ? $quantityOf($perWeekRows) / $perWeekWeeks
                : $quantityOf($rows) / $rows->count())),
            'group' => $group,
            'times_per_week' => round($perWeekRows->count() / $perWeekWeeks, 1),
            'every_weeks' => $group === 'sometimes' ? max(2, (int)round($weeks / $weeksWithIt)) : null,
            'in_list' => isset($open[$key]),
            'unit_price' => $latest->unit_price !== null ? (float)$latest->unit_price : null,
        ];
    }

    // Calendar week counted back from the current one: 1 = last week (Monday to Sunday), 2 = the week before, ...
    // Rounded, so the hour more or less around summer time doesn't shift a week.
    private function weekIndex(GroceryListItem $row, CarbonImmutable $thisWeek): int
    {
        $monday = CarbonImmutable::parse($row->created_at)
            ->setTimezone($thisWeek->getTimezone())
            ->startOfWeek(CarbonImmutable::MONDAY);

        return (int)round($monday->diffInDays($thisWeek) / 7);
    }

    /** @return array<string, true> Lower-cased names of the products open on the list */
    private function openNames(int $listId): array
    {
        $latestIds = GroceryListItem::selectRaw('MAX(id) as id')->where('list_id', $listId)->groupBy('name');

        return GroceryListItem::whereIn('id', $latestIds)
            ->where(fn($query) => $query->where('checked', false)->orWhereNull('checked'))
            ->pluck('name')
            ->mapWithKeys(fn(string $name) => [mb_strtolower(trim($name)) => true])
            ->all();
    }
}
