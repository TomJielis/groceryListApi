<?php

namespace App\Http\Resources;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The conversation as the person who asked sees it: the admin shows up as the app, not by name (load messages)
 *
 * @mixin SupportTicket
 */
class SupportConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'status' => $this->status,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'last_message_at' => $this->last_message_at,
            'messages' => $this->messages->map(fn (SupportMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'from_admin' => $message->from_admin,
                'via' => $message->via,
                'created_at' => $message->created_at,
            ]),
        ];
    }
}
