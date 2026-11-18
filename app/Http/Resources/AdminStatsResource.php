<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A statistic of the admin dashboard: the chosen month and the one before (StatsMonths::compare), without a data
 * wrapper, as the dashboard reads it. The exact fields per statistic are fixed in AdminStatsResponseShapeTest.
 */
class AdminStatsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{current_month: array<string, mixed>, previous_month: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
