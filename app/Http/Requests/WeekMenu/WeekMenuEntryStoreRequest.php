<?php

namespace App\Http\Requests\WeekMenu;

use App\Models\WeekMenuEntry;

// Planning a recipe from the user's recipe book on a day (for its own number of servings when none is given)
class WeekMenuEntryStoreRequest extends WeekMenuRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => WeekMenuEntry::DATE_RULES,
            'recipe_id' => 'required|integer',
            'servings' => 'nullable|integer|min:1|max:' . WeekMenuEntry::MAX_SERVINGS,
        ];
    }

    public function servings(): ?int
    {
        return $this->validated('servings') !== null ? (int) $this->validated('servings') : null;
    }
}
