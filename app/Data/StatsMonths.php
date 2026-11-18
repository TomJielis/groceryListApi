<?php

namespace App\Data;

use Carbon\Carbon;

// The month the admin chose and the one before, which every statistic shows side by side
final readonly class StatsMonths
{
    public function __construct(
        public Carbon $current,
        public Carbon $previous,
    ) {
    }

    /**
     * Both months as the dashboard reads them: each with its period ("2026-10") and what $figures gives for it
     *
     * @param callable(Carbon): array<string, mixed> $figures
     * @return array{current_month: array<string, mixed>, previous_month: array<string, mixed>}
     */
    public function compare(callable $figures): array
    {
        return [
            'current_month' => ['period' => $this->current->format('Y-m'), ...$figures($this->current)],
            'previous_month' => ['period' => $this->previous->format('Y-m'), ...$figures($this->previous)],
        ];
    }
}
