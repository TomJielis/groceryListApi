<?php

namespace App\Http\Resources;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A support ticket with every column: the admin's lists and conversation, and the user's own list. messages_count,
 * user and messages only when loaded (the conversation: load CONVERSATION).
 *
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    // The admin's conversation: who asked (with their language, to answer in it) and which admin wrote each answer
    public const CONVERSATION = ['user:id,name,email,language', 'messages.admin:id,name'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'status' => $this->status,
            'unread_by_user' => $this->unread_by_user,
            'language' => $this->language,
            'last_message_at' => $this->last_message_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'messages_count' => $this->whenCounted('messages'),
            // The columns that were loaded: the lists without the language, the conversation with it
            'user' => $this->whenLoaded('user', fn (User $user) => $user->only(
                array_values(array_intersect(['id', 'name', 'email', 'language'], array_keys($user->getAttributes()))),
            )),
            'messages' => SupportTicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
