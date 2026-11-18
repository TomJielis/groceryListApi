<?php

namespace App\Http\Resources;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A message in the admin's conversation, with the admin who wrote it (load admin:id,name)
 *
 * @mixin SupportMessage
 */
class SupportTicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_admin' => $this->from_admin,
            'body' => $this->body,
            'via' => $this->via,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'admin' => $this->whenLoaded('admin', fn () => $this->admin->only(['id', 'name'])),
        ];
    }
}
