<?php

namespace App\Http\Requests\WeekMenu;

use App\Models\WeekMenu;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

// The planned recipes from one date to another (both included), at most WeekMenu::MAX_DAYS apart
class WeekMenuEntriesRequest extends WeekMenuRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->from()->diffInDays($this->to()) > WeekMenu::MAX_DAYS) {
                    $validator->errors()->add('to', __('week-menus.range_too_long', [], $this->language()));
                }
            },
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('from'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('to'));
    }
}
