<?php

namespace App\Services;

use App\Data\SupportQuestion;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * What happens to a support ticket: asking, writing back and forth (each message tells the other side, see
 * SupportNotifier), reading the answer, and the admin closing or reopening it
 */
class SupportService
{
    public function __construct(private SupportNotifier $notifier)
    {
    }

    public function ask(User $user, SupportQuestion $question): SupportTicket
    {
        // Name and address as they were when asking: the admin sees who asked, also after a name change
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => $question->subject,
            'language' => $question->language ?? $user->language ?? 'nl',
            'status' => SupportTicket::OPEN,
        ]);
        $message = $ticket->addMessage($question->message, $question->via);

        $this->notifier->questionAsked($ticket, $message);

        return $ticket;
    }

    // Writing again reopens a closed ticket
    public function userReplies(SupportTicket $ticket, string $body, string $via): SupportTicket
    {
        $message = $ticket->addMessage($body, $via);
        $this->notifier->userReplied($ticket, $message);

        return $ticket->fresh();
    }

    public function adminReplies(SupportTicket $ticket, string $body, string $via, User $admin): SupportTicket
    {
        $message = $ticket->addMessage($body, $via, $admin);
        $this->notifier->adminReplied($ticket, $message);

        return $ticket->fresh();
    }

    // Opening the conversation reads the answer
    public function markRead(SupportTicket $ticket): SupportTicket
    {
        if ($ticket->unread_by_user) {
            $ticket->update(['unread_by_user' => false]);
        }

        return $ticket;
    }

    // Closing doesn't tell the user; a new message from them opens the ticket again
    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => SupportTicket::CLOSED, 'closed_at' => now()]);

        return $ticket;
    }

    // Back to whoever has to write next
    public function reopen(SupportTicket $ticket): SupportTicket
    {
        $lastFromAdmin = (bool) $ticket->messages()->reorder()->latest('id')->value('from_admin');
        $ticket->update(['status' => $lastFromAdmin ? SupportTicket::ANSWERED : SupportTicket::OPEN, 'closed_at' => null]);

        return $ticket;
    }
}
