<?php

namespace App\Http\Requests\WeekMenu;

use App\Models\WeekMenuEntry;

// Moving a planned recipe to another day, another number of servings, or putting it on the list again
class WeekMenuEntryUpdateRequest extends WeekMenuRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => 'sometimes|' . WeekMenuEntry::DATE_RULES,
            'servings' => 'sometimes|integer|min:1|max:' . WeekMenuEntry::MAX_SERVINGS,
            // "Put on the list again": its check goes, so the next "put on my list" takes it along
            'reset_added' => 'sometimes|boolean',
        ];
    }

    /**
     * What changes on the entry
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = $this->safe()->only(['date', 'servings']);

        return $this->boolean('reset_added') ? [...$changes, ...WeekMenuEntry::NOT_ADDED] : $changes;
    }
}
