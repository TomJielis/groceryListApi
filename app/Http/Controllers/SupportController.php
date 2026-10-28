<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support questions from the app and the website, for signed-in users only (someone who can't log in mails the
 * support address). The person who asked sees and answers their tickets here; the admin in AdminSupportController.
 */
class SupportController extends Controller
{
    public function __construct(private SupportNotifier $notifier)
    {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'language' => ['nullable', 'in:nl,en'],
            'via' => ['nullable', 'in:app,website'],
        ]);

        // Name and address as they were when asking: the admin sees who asked, also after a name change
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => $data['subject'],
            'language' => $data['language'] ?? $user->language ?? 'nl',
            'status' => SupportTicket::OPEN,
        ]);
        $message = $ticket->addMessage($data['message'], $data['via'] ?? 'website');

        $this->notifier->questionAsked($ticket, $message);

        return response()->json(['data' => $this->present($ticket)], 201);
    }

    // The signed-in user's own tickets, newest activity first
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json(['data' => $tickets]);
    }

    // Number of tickets with an answer the user hasn't opened yet (badge in the app)
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->supportTickets()->where('unread_by_user', true)->count()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->present($this->markRead($this->ownTicket($request, $id)))]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        return $this->addReply($request, $this->ownTicket($request, $id));
    }



    private function addReply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'via' => ['nullable', 'in:app,website'],
        ]);

        // Writing again reopens a closed ticket
        $message = $ticket->addMessage($data['message'], $data['via'] ?? 'website');
        $this->notifier->userReplied($ticket, $message);

        return response()->json(['data' => $this->present($ticket->fresh())], 201);
    }

    // Opening the conversation reads the answer
    private function markRead(SupportTicket $ticket): SupportTicket
    {
        if ($ticket->unread_by_user) {
            $ticket->update(['unread_by_user' => false]);
        }

        return $ticket;
    }

    private function ownTicket(Request $request, int $id): SupportTicket
    {
        return $request->user()->supportTickets()->findOrFail($id);
    }


    // The conversation as the person who asked sees it: the admin shows up as the app, not by name
    private function present(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'name' => $ticket->name,
            'email' => $ticket->email,
            'created_at' => $ticket->created_at,
            'last_message_at' => $ticket->last_message_at,
            'messages' => $ticket->messages()->get(['id', 'body', 'from_admin', 'via', 'created_at']),
        ];
    }
}
