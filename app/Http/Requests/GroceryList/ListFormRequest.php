<?php

namespace App\Http\Requests\GroceryList;

use App\Models\GroceryList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The base of the list, list item and week planner requests. Their validation messages are Laravel's own (English), as
 * they always were, so no messages here. What is in the user's language is the 403 for a list they may not see.
 */
abstract class ListFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Items can only be touched on lists the user may see: their own or one they accepted an invite for.
    // GroceryList's global scope applies exactly that rule, so a list someone else owns isn't found.
    protected function ensureListAccess(mixed $listId): void
    {
        abort_unless(
            $listId && GroceryList::whereKey($listId)->exists(),
            403,
            __('lists.no_access', [], $this->language())
        );
    }

    protected function language(): string
    {
        return $this->user()?->language ?: 'nl';
    }
}
