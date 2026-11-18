<?php

namespace App\Http\Resources;

use App\Models\RecipeLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A recipe link that was made (or the maker's working one, given again), for the phone's share sheet
 *
 * @mixin RecipeLink
 */
class RecipeLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->url(),
            'expires_at' => $this->expires_at,
        ];
    }

    // Always 200, new link or the working one given again, as the apps already in the stores expect (a resource of a
    // model made just now would otherwise answer 201)
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}
