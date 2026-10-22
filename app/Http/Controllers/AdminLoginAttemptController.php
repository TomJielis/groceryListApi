<?php

namespace App\Http\Controllers;

use App\Models\InvalidLoginAttempt;
use App\Models\SuccessfulLogin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Failed logins for the admin overview: the attempts themselves (paginated) plus a summary of
 * the same selection, so an attack on one account or from one address stands out.
 */
class AdminLoginAttemptController extends Controller
{
    private const PERIODS = ['24h' => 24, '7d' => 24 * 7, '30d' => 24 * 30];

    // From this many failed attempts, a successful login from the same address afterwards is a warning sign
    private const SUSPICIOUS_FAILURES = 3;

    // Requests from this machine itself: every old record (and website logins before the website passed the
    // visitor's address on) has one of these, so they would only give false alarms
    private const LOCAL_ADDRESSES = ['127.0.0.1', '::1'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|in:24h,7d,30d,all',
            'account' => 'sometimes|in:known,unknown',
            'email' => 'sometimes|string|max:255',
            'ip' => 'sometimes|string|max:45',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $filtered = fn () => $this->filtered($request);

        $paginator = $filtered()
            ->with('user:id,name,email')
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (InvalidLoginAttempt $attempt) => [
                'id' => $attempt->id,
                // Rows from before the username column only have the account
                'email' => $attempt->username ?? $attempt->user?->email,
                'user' => $attempt->user ? ['id' => $attempt->user->id, 'name' => $attempt->user->name] : null,
                'ip_address' => $attempt->ip_address,
                'attempted_at' => $attempt->attempted_at,
                // This attempt locked the account for 15 minutes
                'caused_lockout' => $attempt->blocked_till !== null,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => [
                'attempts' => $paginator->total(),
                'accounts' => $filtered()->distinct()->count('username'),
                'ip_addresses' => $filtered()->distinct()->count('ip_address'),
                'lockouts' => $filtered()->whereNotNull('blocked_till')->count(),
                'top_accounts' => $filtered()
                    ->selectRaw('username, user_id, COUNT(*) as attempts, COUNT(DISTINCT ip_address) as ip_addresses, MAX(attempted_at) as last_attempt')
                    ->groupBy('username', 'user_id')
                    ->orderByDesc('attempts')
                    ->limit(5)
                    ->get()
                    ->load('user:id,email')
                    ->map(fn ($row) => [
                        'email' => $row->username ?? $row->user?->email,
                        'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                        'attempts' => (int) $row->attempts,
                        'ip_addresses' => (int) $row->ip_addresses,
                        'last_attempt' => Carbon::parse($row->last_attempt)->toJSON(),
                    ]),
                'top_ip_addresses' => $filtered()
                    ->selectRaw('ip_address, COUNT(*) as attempts, COUNT(DISTINCT username) as accounts, MAX(attempted_at) as last_attempt')
                    ->groupBy('ip_address')
                    ->orderByDesc('attempts')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row) => [
                        'ip_address' => $row->ip_address,
                        'attempts' => (int) $row->attempts,
                        'accounts' => (int) $row->accounts,
                        'last_attempt' => Carbon::parse($row->last_attempt)->toJSON(),
                    ]),
                'suspicious' => $this->suspicious($request),
            ],
        ]);
    }

    /**
     * Addresses with several failed attempts that afterwards logged in successfully: someone may have
     * guessed a password. Per address, the accounts that logged in from there after the first failure.
     */
    private function suspicious(Request $request): array
    {
        return $this->filtered($request)
            ->whereNotIn('ip_address', self::LOCAL_ADDRESSES)
            ->selectRaw('ip_address, COUNT(*) as attempts, MIN(attempted_at) as first_attempt')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= ?', [self::SUSPICIOUS_FAILURES])
            ->orderByDesc('attempts')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $logins = SuccessfulLogin::with('user:id,name,email')
                    ->where('ip_address', $row->ip_address)
                    ->where('logged_in_at', '>=', $row->first_attempt)
                    ->orderByDesc('logged_in_at')
                    ->get();

                return $logins->isEmpty() ? null : [
                    'ip_address' => $row->ip_address,
                    'failed_attempts' => (int) $row->attempts,
                    'accounts' => $logins->pluck('user')->filter()->unique('id')
                        ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
                        ->values(),
                    'last_login' => $logins->first()->logged_in_at,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    // The same filters for the list and every summary number
    private function filtered(Request $request): Builder
    {
        $period = $request->query('period', '7d');

        return InvalidLoginAttempt::query()
            ->when(isset(self::PERIODS[$period]), fn ($q) => $q->where('attempted_at', '>=', now()->subHours(self::PERIODS[$period])))
            ->when($request->query('account') === 'known', fn ($q) => $q->whereNotNull('user_id'))
            ->when($request->query('account') === 'unknown', fn ($q) => $q->whereNull('user_id'))
            ->when($request->filled('email'), fn ($q) => $q->where('username', 'like', '%' . $request->query('email') . '%'))
            ->when($request->filled('ip'), fn ($q) => $q->where('ip_address', $request->query('ip')));
    }
}
