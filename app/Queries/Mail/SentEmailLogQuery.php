<?php

namespace App\Queries\Mail;

use App\Models\SentEmail;
use Illuminate\Database\Eloquent\Builder;

/**
 * The log of sent mails for the admin, newest first, with who caused each one
 */
class SentEmailLogQuery
{
    public function query(?string $status, ?string $type, ?string $dateFrom, ?string $dateTo): Builder
    {
        return SentEmail::with('triggeredBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($dateFrom !== null, fn ($q) => $q->whereDate('sent_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->whereDate('sent_at', '<=', $dateTo));
    }
}
