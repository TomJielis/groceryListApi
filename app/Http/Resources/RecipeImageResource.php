<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where the app loads a recipe's photo and its small copy (after a new photo)
 *
 * @mixin Recipe
 */
class RecipeImageResource extends JsonResource
{
    /**
     * @return array<string, string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'image_url' => $this->imageUrl(),
            'thumb_url' => $this->imageUrl(true),
        ];
    }
}
