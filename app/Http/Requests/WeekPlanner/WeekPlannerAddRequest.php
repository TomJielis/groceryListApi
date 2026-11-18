<?php

namespace App\Http\Requests\WeekPlanner;

use App\Http\Requests\GroceryList\ListFormRequest;

// The products ticked in the planner, all in one go. The list ({id}) is checked before the fields, as it always was.
class WeekPlannerAddRequest extends ListFormRequest
{
    // Like moving products (GroceryListItemMoveRequest); the app sends all ticked products in one go
    private const MAX_PRODUCTS = 500;
    // The planner's stepper in the app stops at 99 as well
    private const MAX_QUANTITY = 99;

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
        return [
            'items' => 'required|array|min:1|max:' . self::MAX_PRODUCTS,
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:' . self::MAX_QUANTITY,
        ];
    }

    /**
     * As sent (a quantity of "2" stays "2" in the items answered, as it always did)
     *
     * @return array<int, array{name: string, quantity: int|string}>
     */
    public function products(): array
    {
        return $this->validated('items');
    }
}
