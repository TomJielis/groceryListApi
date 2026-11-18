<?php

namespace App\Queries\Users;

use App\Models\InvalidLoginAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The admin users overview: one page of users with their activity, lists and recent failed logins, and the counts of
 * all users on top
 */
class UserOverviewQuery
{
    public function page(?string $search, ?string $status, ?string $terms, string $sort, int $perPage): LengthAwarePaginator
    {
        $termsVersion = config('app.terms_version');

        $query = User::select('id', 'name', 'email', 'created_at', 'email_verified_at', 'accepted_terms_version', 'blocked')
            ->withActivity()
            ->addSelect(['failed_logins' => InvalidLoginAttempt::selectRaw('COUNT(*)')
                ->whereColumn('user_id', 'users.id')
                ->where('attempted_at', '>=', now()->subDays(7))])
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'inactive', fn ($q) => $q->whereNot(fn ($q) => $q->active()))
            ->when($status === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($status === 'blocked', fn ($q) => $q->where('blocked', true))
            ->when($terms === 'current', fn ($q) => $q->where('accepted_terms_version', $termsVersion))
            ->when($terms === 'outdated', fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('accepted_terms_version')
                ->orWhere('accepted_terms_version', '!=', $termsVersion)));

        // Never-active users go last when sorting on activity (MySQL puts NULL last when descending)
        match ($sort) {
            'newest' => $query->orderByDesc('created_at'),
            'lists' => $query->orderByDesc('lists_count')->orderByDesc('created_at'),
            default => $query->orderByDesc('last_active')->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }

    /**
     * All users, whatever the filters: tapping a number filters on it
     *
     * @return array{total: int, active: int, unverified: int, blocked: int, terms_version: mixed}
     */
    public function summary(): array
    {
        return [
            'total' => User::count(),
            'active' => User::active()->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'blocked' => User::where('blocked', true)->count(),
            'terms_version' => config('app.terms_version'),
        ];
    }
}
