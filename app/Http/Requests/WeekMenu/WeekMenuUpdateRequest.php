<?php

namespace App\Http\Requests\WeekMenu;

// Renaming a week menu (its creator)
class WeekMenuUpdateRequest extends WeekMenuRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:60',
        ];
    }

    public function menuName(): string
    {
        return trim((string) $this->validated('name'));
    }
}
