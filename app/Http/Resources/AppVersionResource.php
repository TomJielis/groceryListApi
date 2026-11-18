<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The oldest app version and build that still work and where to update, per platform (the config/app_version.php
 * array). Cast here: the values come from .env as strings.
 */
class AppVersionResource extends JsonResource
{
    /**
     * @return array<string, array{minimum_version: string, minimum_build: int, update_url: string}>
     */
    public function toArray(Request $request): array
    {
        return ['ios' => $this->platform('ios'), 'android' => $this->platform('android')];
    }

    private function platform(string $name): array
    {
        return [
            'minimum_version' => (string) data_get($this->resource, "minimum.$name"),
            'minimum_build' => (int) data_get($this->resource, "minimum_build.$name"),
            'update_url' => (string) data_get($this->resource, "update_url.$name"),
        ];
    }
}
