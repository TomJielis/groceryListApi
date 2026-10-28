<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\SupportNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    public function __construct(private SupportNotifier $notifier)
    {
    }

    /**
     * Tickets, waiting ones first by default. ?status=open|answered|closed filters; meta.counts has the number per status
     * (open = waiting for an answer, for a badge).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:open,answered,closed']]);

        $paginator = SupportTicket::query()
            ->withCount('messages')
            ->with('user:id,name,email')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . addcslashes((string) $request->query('search'), '%_\\') . '%';
                $q->where(fn ($q) => $q->where('subject', 'like', $term)->orWhere('email', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->orderByDesc('last_message_at')
            ->paginate(max(1, min((int) $request->query('per_page', 20), 100)));

        $counts = SupportTicket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'counts' => [
                    'open' => (int) ($counts['open'] ?? 0),
                    'answered' => (int) ($counts['answered'] ?? 0),
                    'closed' => (int) ($counts['closed'] ?? 0),
                ],
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->present(SupportTicket::findOrFail($id))]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $ticket = SupportTicket::findOrFail($id);

        $message = $ticket->addMessage($data['message'], $request->input('via') === 'app' ? 'app' : 'website', $request->user());
        $this->notifier->adminReplied($ticket, $message);

        return response()->json(['data' => $this->present($ticket->fresh())], 201);
    }

    // Closing doesn't tell the user; a new message from them opens the ticket again
    public function close(int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => SupportTicket::CLOSED, 'closed_at' => now()]);

        return response()->json(['data' => $this->present($ticket)]);
    }

    public function reopen(int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $lastFromAdmin = (bool) $ticket->messages()->reorder()->latest('id')->value('from_admin');
        $ticket->update(['status' => $lastFromAdmin ? SupportTicket::ANSWERED : SupportTicket::OPEN, 'closed_at' => null]);

        return response()->json(['data' => $this->present($ticket)]);
    }

    private function present(SupportTicket $ticket): array
    {
        $ticket->load(['user:id,name,email,language', 'messages.admin:id,name']);

        return $ticket->toArray();
    }
}
