<?php

namespace App\Http\Controllers;

use App\Models\InvalidLoginAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The admin users overview: search, filters, sorting and paging on the server, with counts of all
 * users on top. (GET admin/users keeps its old shape for the website.)
 */
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive,unverified,blocked',
            'terms' => 'sometimes|in:current,outdated',
            'sort' => 'sometimes|in:last_active,newest,lists',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $termsVersion = config('app.terms_version');
        $search = $request->query('search');
        $status = $request->query('status');
        $terms = $request->query('terms');

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
        match ($request->query('sort', 'last_active')) {
            'newest' => $query->orderByDesc('created_at'),
            'lists' => $query->orderByDesc('lists_count')->orderByDesc('created_at'),
            default => $query->orderByDesc('last_active')->orderByDesc('created_at'),
        };

        $paginator = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'email_verified' => $user->email_verified_at !== null,
                'terms_version' => $user->accepted_terms_version,
                'terms_current' => $user->accepted_terms_version === $termsVersion,
                'last_active' => $user->last_active ? Carbon::parse($user->last_active)->toJSON() : null,
                'lists_count' => (int) $user->lists_count,
                'failed_logins' => (int) $user->failed_logins,
                'blocked' => (bool) $user->blocked,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            // All users, whatever the filters: tapping a number filters on it
            'summary' => [
                'total' => User::count(),
                'active' => User::active()->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
                'blocked' => User::where('blocked', true)->count(),
                'terms_version' => $termsVersion,
            ],
        ]);
    }
}
