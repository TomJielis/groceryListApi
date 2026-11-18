<?php

namespace App\Http\Requests\WeekPlanner;

use App\Http\Requests\GroceryList\ListFormRequest;

// ?timezone=Europe/Amsterdam: the phone's time zone, so a week is Monday to Sunday where the user is. The list ({id})
// is checked before the time zone (authorize runs before validation), as it always was.
class WeekPlannerShowRequest extends ListFormRequest
{
    public function authorize(): bool
    {
        $this->ensureListAccess((int) $this->route('id'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['timezone' => 'nullable|string|timezone:all'];
    }

    public function timezone(): ?string
    {
        return $this->query('timezone');
    }
}
