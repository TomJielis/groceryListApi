<?php

namespace App\Http\Requests\WeekPlanner;

use App\Http\Requests\GroceryList\ListFormRequest;

// Switching the planner button in the app on or off
class WeekPlannerSettingRequest extends ListFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['enabled' => 'required|boolean'];
    }

    public function enabled(): bool
    {
        return (bool) $this->validated('enabled');
    }
}
