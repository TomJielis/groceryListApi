<?php

namespace App\Http\Requests\WeekMenu;

// Claiming dishes for "put on my list" (or with undo giving the user's own claim back)
class WeekMenuAddedToListRequest extends WeekMenuRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_ids' => 'required|array|min:1|max:200',
            'entry_ids.*' => 'integer',
            // The list they went on (shown: "On 'Boodschappen' by Tom"); one the user can see
            'list_id' => 'nullable|integer',
            'undo' => 'sometimes|boolean',
        ];
    }

    /**
     * @return list<int>
     */
    public function entryIds(): array
    {
        return array_map('intval', $this->validated('entry_ids'));
    }

    public function listId(): ?int
    {
        return $this->validated('list_id') !== null ? (int) $this->validated('list_id') : null;
    }

    public function undo(): bool
    {
        return $this->boolean('undo');
    }
}
