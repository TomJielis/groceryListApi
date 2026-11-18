<?php

namespace App\Http\Controllers;

use App\Http\Requests\Support\SupportReplyRequest;
use App\Http\Requests\Support\SupportTicketStoreRequest;
use App\Http\Resources\SupportConversationResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Support questions from the app and the website, for signed-in users only (someone who can't log in mails the
 * support address). The person who asked sees and answers their tickets here; the admin in AdminSupportController.
 */
class SupportController extends Controller
{
    public function __construct(private SupportService $support)
    {
    }

    // 201, as a ticket made just now
    public function store(SupportTicketStoreRequest $request): SupportConversationResource
    {
        return $this->conversation($this->support->ask($request->user(), $request->question()));
    }

    // The signed-in user's own tickets, newest activity first
    public function index(Request $request): AnonymousResourceCollection
    {
        $tickets = $request->user()->supportTickets()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get();

        return SupportTicketResource::collection($tickets);
    }

    // Number of tickets with an answer the user hasn't opened yet (badge in the app)
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->supportTickets()->where('unread_by_user', true)->count()]);
    }

    public function show(Request $request, int $id): SupportConversationResource
    {
        $ticket = $request->user()->supportTickets()->findOrFail($id);

        return $this->conversation($this->support->markRead($ticket));
    }

    public function reply(SupportReplyRequest $request): JsonResponse
    {
        $ticket = $this->support->userReplies($request->ticket(), $request->message(), $request->via());

        return $this->conversation($ticket)->response()->setStatusCode(201);
    }

    private function conversation(SupportTicket $ticket): SupportConversationResource
    {
        return new SupportConversationResource($ticket->load('messages'));
    }
}
