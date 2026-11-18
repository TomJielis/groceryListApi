<?php

namespace App\Data;

use Carbon\Carbon;

// The figures of one month in "My statistics" (UserStatsQuery), shown side by side with the month before
final readonly class UserStatsMonth
{
    /**
     * @param list<array{id: int, name: string, total: float}> $spendByList
     * @param list<array{user_id: int, user_name: string, checked: int, added: int}> $userBreakdown
     */
    public function __construct(
        public Carbon $month,
        public int $added,
        public int $checked,
        public float $spendTotal,
        public array $spendByList,
        public array $userBreakdown,
        public int $invalidLoginAttempts,
    ) {
    }
}
