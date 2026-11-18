<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminSupportIndexRequest;
use App\Http\Requests\Admin\AdminSupportReplyRequest;
use App\Http\Resources\PageMeta;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Queries\Support\AdminTicketListQuery;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminSupportController extends Controller
{
    public function __construct(private SupportService $support)
    {
    }

    /**
     * Tickets, waiting ones first by default. ?status=open|answered|closed filters; meta.counts has the number per status
     * (open = waiting for an answer, for a badge).
     */
    public function index(AdminSupportIndexRequest $request, AdminTicketListQuery $tickets): AnonymousResourceCollection
    {
        $paginator = $tickets->page($request->status(), $request->search(), $request->perPage());

        return SupportTicketResource::collection($paginator->getCollection())->additional([
            'meta' => [...PageMeta::of($paginator), 'counts' => $tickets->counts()],
        ]);
    }

    public function show(int $id): SupportTicketResource
    {
        return $this->conversation(SupportTicket::findOrFail($id));
    }

    public function reply(AdminSupportReplyRequest $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket = $this->support->adminReplies($ticket, $request->message(), $request->via(), $request->user());

        return $this->conversation($ticket)->response()->setStatusCode(201);
    }

    public function close(int $id): SupportTicketResource
    {
        return $this->conversation($this->support->close(SupportTicket::findOrFail($id)));
    }

    public function reopen(int $id): SupportTicketResource
    {
        return $this->conversation($this->support->reopen(SupportTicket::findOrFail($id)));
    }

    private function conversation(SupportTicket $ticket): SupportTicketResource
    {
        return new SupportTicketResource($ticket->load(SupportTicketResource::CONVERSATION));
    }
}
