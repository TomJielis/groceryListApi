<?php

namespace App\Queries\Support;

use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The admin's support tickets: waiting ones first, and the number per status (open = waiting for an answer, for a badge)
 */
class AdminTicketListQuery
{
    public function page(?string $status, ?string $search, int $perPage): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->withCount('messages')
            ->with('user:id,name,email')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($search !== null, function ($q) use ($search) {
                $term = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn ($q) => $q->where('subject', 'like', $term)->orWhere('email', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    /**
     * @return array{open: int, answered: int, closed: int}
     */
    public function counts(): array
    {
        $counts = SupportTicket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'open' => (int) ($counts['open'] ?? 0),
            'answered' => (int) ($counts['answered'] ?? 0),
            'closed' => (int) ($counts['closed'] ?? 0),
        ];
    }
}
