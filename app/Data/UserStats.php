<?php

namespace App\Data;

// "My statistics" of the chosen month and the one before (UserStatsQuery), as UserStatsResource shows it
final readonly class UserStats
{
    /**
     * @param list<array{name: string, count: int}> $mostAdded Of the chosen month only
     * @param list<array{name: string, count: int}> $mostChecked Of the chosen month only
     * @param array<string, mixed> $recipes RecipeStatsQuery::forUser
     * @param list<string> $availableMonths
     */
    public function __construct(
        public UserStatsMonth $current,
        public UserStatsMonth $previous,
        public array $mostAdded,
        public array $mostChecked,
        public array $recipes,
        public array $availableMonths,
    ) {
    }
}
