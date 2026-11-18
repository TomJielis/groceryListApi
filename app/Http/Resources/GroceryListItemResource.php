<?php

namespace App\Http\Resources;

use App\Models\GroceryListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item of a list, with its fields as stored: no casts, so checked is 0/1 from the database, or what the app sent
 * when it was just changed (true, "1", ...), and a quantity sent as "2" stays "2". The apps read it that way.
 *
 * @mixin GroceryListItem
 */
class GroceryListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'list_id' => $this->list_id,
            // An item made just now hasn't read checked back from the database (its default): left out, as it always was
            'checked' => $this->whenHas('checked'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Only set when made or changed by a logged in user (older rows have them as null)
            'created_by' => $this->whenHas('created_by'),
            'updated_by' => $this->whenHas('updated_by'),
        ];
    }

    // Always 200, also for an item made just now, as the apps already in the stores expect (a resource of a model made
    // just now would otherwise answer 201)
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}
