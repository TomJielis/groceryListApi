<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support questions from the app and the website.
 *
 * Asking works with and without an account (someone who can't log in must still be able to ask). Without an account the
 * conversation is reached through the secret link in the emails (/support/t/{token}); a signed-in user also finds
 * their own tickets under /support/tickets.
 */
class SupportController extends Controller
{
    public function __construct(private SupportNotifier $notifier)
    {
    }

    public function store(Request $request): JsonResponse
    {
        // The route skips the auth middleware so guests can ask too; a token that is sent along still counts
        /** @var User|null $user */
        $user = auth('sanctum')->user();

        $data = $request->validate([
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:100'],
            'email' => [$user ? 'nullable' : 'required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'language' => ['nullable', 'in:nl,en'],
            'via' => ['nullable', 'in:app,website'],
            // Hidden field that people never see: a bot fills in every field
            'company' => ['prohibited'],
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $user?->id,
            'name' => $user->name ?? $data['name'],
            'email' => $user->email ?? $data['email'],
            'subject' => $data['subject'],
            'language' => $data['language'] ?? $user?->language ?? 'nl',
            'status' => SupportTicket::OPEN,
        ]);
        $message = $ticket->addMessage($data['message'], $data['via'] ?? 'website');

        $this->notifier->questionAsked($ticket, $message);

        return response()->json([
            'data' => $this->present($ticket) + ['access_token' => $ticket->access_token],
        ], 201);
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

    // Through the link in the emails: no login needed
    public function showByToken(string $token): JsonResponse
    {
        return response()->json(['data' => $this->present($this->markRead($this->ticketByToken($token)))]);
    }

    public function replyByToken(Request $request, string $token): JsonResponse
    {
        return $this->addReply($request, $this->ticketByToken($token));
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

    private function ticketByToken(string $token): SupportTicket
    {
        return SupportTicket::where('access_token', $token)->firstOrFail();
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
