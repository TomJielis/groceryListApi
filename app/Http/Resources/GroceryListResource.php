<?php

namespace App\Http\Resources;

use App\Models\GroceryList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A list just made or renamed. The overview shows more (GroceryListOverviewResource).
 *
 * @mixin GroceryList
 */
class GroceryListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // The creator's id here; the overview has the creator's id and name in it
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    // Always 200, also for a list made just now, as the apps already in the stores expect (a resource of a model made
    // just now would otherwise answer 201)
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}
