<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product the list gets (nearly) every week or now and then, as WeekPlanner::forList works it out
 *
 * @property array{name: string, quantity: int, group: string, times_per_week: float, every_weeks: int|null,
 *                 in_list: bool, unit_price: float|null} $resource
 */
class WeekPlannerProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'quantity' => $this->resource['quantity'],
            // weekly or sometimes
            'group' => $this->resource['group'],
            'times_per_week' => $this->resource['times_per_week'],
            // Only for sometimes: about every how many weeks
            'every_weeks' => $this->resource['every_weeks'],
            // Still open on the list right now
            'in_list' => $this->resource['in_list'],
            'unit_price' => $this->resource['unit_price'],
        ];
    }
}
