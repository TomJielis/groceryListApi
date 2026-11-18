<?php

namespace App\Http\Resources;

use App\Models\SentEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A logged mail for the admin, every column (load triggeredBy:id,name,email)
 *
 * @mixin SentEmail
 */
class SentEmailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'recipient_email' => $this->recipient_email,
            'recipient_name' => $this->recipient_name,
            'subject' => $this->subject,
            'body_html' => $this->body_html,
            'body_text' => $this->body_text,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'triggered_by_user_id' => $this->triggered_by_user_id,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'triggered_by' => $this->whenLoaded('triggeredBy', fn () => $this->triggeredBy->only(['id', 'name', 'email'])),
        ];
    }
}
