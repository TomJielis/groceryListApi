<?php

namespace App\Http\Requests\WeekMenu;

// A new week menu; without a name it's called "Weekmenu"
class WeekMenuStoreRequest extends WeekMenuRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:60',
        ];
    }

    public function menuName(): string
    {
        return trim((string) $this->validated('name')) ?: __('week-menus.default_name', [], $this->language());
    }
}
